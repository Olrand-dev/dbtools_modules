<?php

declare(strict_types=1);

namespace NutixApp\GlobalQueue\Src;


use NutixApp\Core\Src\App;
use NutixApp\Core\Src\Db\NPDO;
use NutixApp\Core\Src\Utils\DateHelper;
use NutixApp\GlobalQueue\GlobalQueue;
use NutixApp\Users\Src\UsersController;
use NutixApp\Users\Users;

abstract class GlobalTasksController 
{


    public static function getNextTaskId() : int
    {
        $taskAtWorkId = (int) App::$storage->get('global_task_at_work');

        if (empty($taskAtWorkId)) {
            
            $taskData = NPDO::$models->globalTasks->row(
                "SELECT `id` FROM %table% 
                WHERE `status` LIKE ? 
                ORDER BY `priority` DESC, `create_time` DESC LIMIT 1",
                [GlobalQueue::TASK_STATUS_NEW]
            );
            return (!empty($taskData)) ? $taskData['id'] : 0;

        } else return $taskAtWorkId;
    }


    public static function getTaskInstance(int $id) : GlobalTask
    {
        $type = GlobalTask::getTaskType($id);
        $taskClass = GlobalQueue::TASKS_MAP[$type];
        $storageType = GlobalTask::getTaskStorageType($id);

        if ($storageType === GlobalTask::TASK_DATA_STORAGE_TYPE_FILE) {
            $task = new $taskClass($id, false);
        } else {
            $task = new $taskClass($id);
        }

        return $task;
    }


    public static function moveTaskToFailed(int $taskId, $details = '') : void
    {
        NPDO::connect(MAIN_DB_CONNECTION);
        $failedStatus = GlobalQueue::TASK_STATUS_FAILED;
        $comment = (is_array($details)) ? json_encode($details) : $details;

        NPDO::$models->globalTasks->execute(
            "UPDATE %table% SET `status` = ?, `comment` = ? WHERE `id` = ?",
            [$failedStatus, $comment, $taskId]
        );

        if ($taskId == App::$storage->get('global_task_at_work')) {
            App::$storage->set('global_task_at_work', null);
        }

        $task = self::getTaskInstance($taskId);
        $task->onFailed();

        $type = $task->type;
        if (in_array($type, [
            GlobalQueue::TASK_TYPE_IMPORT_CONTRACTOR_PRODS,
            GlobalQueue::TASK_TYPE_IMPORT_NOMENCLATURE_CONTROL_FILE,
        ])) {
            $contractorId = (int) $task->code;
            $importType = self::getTaskImportType($type, $contractorId);

            GlobalTasksController::handleImportProductsStatData(
                $contractorId,
                $importType,
                GlobalQueue::PROD_IMPORT_STATUS_NOT_COMPLETED
            );
        }
    }


    public static function getTaskImportType(string $type, int $code) : string
    {
        $importType = '';

        if (in_array($type, [
            GlobalQueue::TASK_TYPE_IMPORT_CONTRACTOR_PRODS,
            GlobalQueue::TASK_TYPE_IMPORT_NOMENCLATURE_CONTROL_FILE,
        ])) {
            $contractorId = $code;

            if ($type === GlobalQueue::TASK_TYPE_IMPORT_CONTRACTOR_PRODS) {
                $importType = 'contractor_products';
            } else {
                if ($contractorId === 1) {
                    $importType = 'our_control_file';
                } else $importType = 'contractor_control_file';
            }
        }
        return $importType;
    }


    public static function deleteFinishedTaskFiles() : void
    {
        $ids = NPDO::$models->globalTasks->col(
            "SELECT `id` FROM %table% WHERE `status` = ? AND `files_removed` = 0",
            [GlobalQueue::TASK_STATUS_FINISHED], 
            'int'
        );

        if (!empty($ids)) {
            foreach ($ids as $taskId) {
                $taskStorageType = GlobalTask::getTaskStorageType($taskId);
                if ($taskStorageType === GlobalTask::TASK_DATA_STORAGE_TYPE_DB) continue;

                $task = self::getTaskInstance($taskId);
                $task->removeTaskFiles();
                $task->filesRemoved = 1;
                $task->save(true);
            }
        }
    }


    public static function handleImportProductsStatData(int $contractorId, string $type, string $newStatus) : void
    {
        $storageField = GlobalQueue::PROD_IMPORT_STAT_STORAGE_FIELD;
        $contractors = NPDO::$models->contractors;

        $initStatData = [
            'start_time' => 0,
            'author' => '',
            'author_id' => '',
            'status' => '',
        ];

        $data = $contractors->val($storageField, '`id` = ?', [$contractorId]);
        if (empty($data)) {
            $data = [
                'our_control_file' => $initStatData,
                'contractor_control_file' => $initStatData,
                'contractor_products' => $initStatData,
            ];
            $contractors->update([$storageField => json_encode($data)], $contractorId);
        } else $data = json_decode($data, true);

        if (App::$userAlias === Users::APP_USER_CRON) {
            $user = Users::APP_USER_CRON;
            $userId = SYSTEM_USER;
        } else {
            $userData = UsersController::getProfileData(App::$session->userId);
            $user = $userData['login'];
            $userId = $userData['id'];
        }

        $newStatData = $initStatData;

        if ($newStatus === GlobalQueue::PROD_IMPORT_STATUS_IN_PROGRESS) {
            $newStatData['start_time'] = DateHelper::nowFormated();
            $newStatData['author'] = $user;
            $newStatData['author_id'] = $userId;
        } else $newStatData = $data[$type];
        $newStatData['status'] = $newStatus;

        $data[$type] = $newStatData;
        $contractors->update([$storageField => json_encode($data)], $contractorId);
    }


    /**
     * @return array
     */
    public static function getList(string $type, string $status, string $sort, int $page, int $perPage) : array
    {
        $sql = "SELECT `id`, `type` FROM %table% WHERE 1";
        $bindings = [];

        if ($type !== 'all') {
            $sql .= " AND `type` LIKE ?";
            $bindings[] = $type;
        }

        $sql .= " AND `status` LIKE ?";
        $bindings[] = $status;

        switch ($sort) {

            case 'by_priority_desc': {
                $sql .= " ORDER BY `priority` DESC";
                break;
            }
            case 'by_priority_asc': {
                $sql .= " ORDER BY `priority` ASC";
                break;
            }
            case 'by_date_desc': {
                $sql .= " ORDER BY `create_time` DESC";
                break;
            }
            case 'by_date_asc': {
                $sql .= " ORDER BY `create_time` ASC";
                break;
            }
        }

        $offset = ($page - 1) * $perPage;
        $sql .= " LIMIT $offset, $perPage";

        $tasksData = NPDO::$models->globalTasks->rows($sql, $bindings);
        if (empty($tasksData)) return [];

        $data = array_map(function($taskData) use ($type) {

            if ($type === 'all') {
                $taskClass = GlobalQueue::TASKS_MAP[$taskData['type']];
            } else {
                $taskClass = GlobalQueue::TASKS_MAP[$type];
            }

            $task = new $taskClass((int) $taskData['id']);
            return $task->getFieldsData();
            
        }, $tasksData);

        return $data;
    }


    /**
     * @return array
     */
    public static function getPaginationData(string $type, string $status, int $perPage) : array
    {
        $model = NPDO::$models->globalTasks;
        $model->addCustomFields(['tasks_count' => 'int']);

        $sql = "SELECT COUNT(*) AS `tasks_count` FROM %table% WHERE 1";
        $bindings = [];

        if ($type !== 'all') {
            $sql .= " AND `type` LIKE ?";
            $bindings[] = $type;
        }

        $sql .= " AND `status` LIKE ?";
        $bindings[] = $status;

        $count = NPDO::$models->globalTasks->row($sql, $bindings)['tasks_count'];

        return [
            'pages_count' => ($perPage > 0) ? ceil($count / $perPage) : 0,
            'page_range' => GlobalQueue::PAGINATION_PAGE_RANGE,
        ];
    }

}