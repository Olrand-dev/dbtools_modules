<?php

declare(strict_types=1);

namespace NutixApp\GlobalQueue\Src;

use NutixApp\Core\Src\App;
use NutixApp\Core\Src\Db\NPDO;
use NutixApp\Core\Src\Utils\DateHelper;
use NutixApp\GlobalQueue\GlobalQueue;

class TaskRunner 
{
    
    /**
     * @var GlobalTask
     */
    public $task;

    /**
     * @var int
     */
    private $dataLength;

    /**
     * @var string
     */
    private $taskStorageType;

    /**
     * @var int
     */
    private $offset;

    /**
     * @var int
     */
    private $limit;


    public function __construct(int $id = 0)
    {
        if (!empty($id)) {
            $this->setTask(GlobalTasksController::getTaskInstance($id));
        }
    }


    public function findTask(string $type = '', string $code = '') : bool
    {
        $bindings = [];

        $sql = "SELECT `id` FROM %table% WHERE 1";
        $bindings[] = $type;

        if (!empty($type)) {
            $sql .= " AND `type` LIKE ?";
            $bindings[] = $type;
        }

        if (!empty($code)) {
            $sql .= " AND `code` LIKE ?";
            $bindings[] = $code;
        }

        $sql .= " AND `status` != ? ORDER BY `priority` DESC, `create_time` DESC LIMIT 1";
        $bindings[] = GlobalQueue::TASK_STATUS_FINISHED;

        $taskData = NPDO::$models->globalTasks->row($sql, $bindings);
        if (empty($taskData)) return false;

        $taskClass = GlobalQueue::TASKS_MAP[$type];
        $task = new $taskClass($taskData['id']);
        $this->setTask($task);

        return true;
    }


    private function setTask(GlobalTask $task) : void
    {
        $this->task = $task;

        $this->taskStorageType = GlobalTask::getTaskStorageType($this->task->id);
        $this->dataLength = $this->task->getDataLength();
        $this->offset = $this->task->cursor;
        $this->limit = $this->task->limit;
    }


    public function taskExists() : bool
    {
        return !empty($this->task);
    }


    public function getDataChunk() : array
    {
        if ($this->task->cursor === 0) {
            $this->task->status = GlobalQueue::TASK_STATUS_AT_WORK;
            $this->saveTask(true);
        }

        $data = $this->task->getDataChunk($this->offset, $this->limit);
        return $data;
    }


    public function moveCursor() : void 
    {
        $this->task->cursor = $this->offset + $this->limit;
    }


    public function taskCompleted() : bool
    {
        return $this->task->cursor >= $this->dataLength;
    }


    public function saveTask(bool $onlyInfo = false) : void
    {
        $this->task->save($onlyInfo);
    }


    public function finishTask(bool $deleteTaskData = true) : void
    {
        $this->task->finished = true;
        $this->task->finishDatetime = DateHelper::nowFormated('d-m-Y, H:i:s');
        $this->task->status = GlobalQueue::TASK_STATUS_FINISHED;

        $this->task->save();
        if ($deleteTaskData) {
            $this->deleteTaskData();
        }

        if ($this->task->id == App::$storage->get('global_task_at_work')) {
            App::$storage->set('global_task_at_work', null);
        }
    }


    public function deleteTask() : void
    {
        $this->task->onDelete();

        if ($this->task->id == App::$storage->get('global_task_at_work')) {
            App::$storage->set('global_task_at_work', null);
        }

        $this->deleteTaskData();

        NPDO::$models->globalTasks->deleteRow($this->task->id);
    }


    private function deleteTaskData() : void
    {
        if ($this->taskStorageType === GlobalTask::TASK_DATA_STORAGE_TYPE_FILE) {
            $this->task->removeTaskFiles();
            //$this->task->filesRemoved = 1;
        } else {
            $this->task->deleteTempTables();
        }
    }
    
}