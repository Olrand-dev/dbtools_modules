<?php

declare(strict_types=1);

namespace NutixApp\GlobalQueue\Src;

use NutixApp\Core\Src\Db\NPDO;
use NutixApp\GlobalQueue\GlobalQueue;

abstract class GlobalTask
{

    public const TASK_DATA_STORAGE_TYPE_FILE = 'file';
    public const TASK_DATA_STORAGE_TYPE_DB = 'db';

    /**
     * Действие при удалении задачи
     */
    public function onDelete() : void {}

    /**
     * Действие при переводе задачи в проблемные
     */
    public function onFailed() : void {}


    public static function getTaskType(int $id) : string
    {
        return (string) NPDO::$models->globalTasks->val(
            'type', '`id` = ?', [$id]
        );
    }


    public static function getTaskStorageType(int $id) : string
    {
        $type = self::getTaskType($id);
        $taskClass = GlobalQueue::TASKS_MAP[$type];
        return $taskClass::DATA_STORAGE_TYPE;
    }


    /**
     * Базовый метод сортировки отчета задачи
     * @param array $report
     * @return array
     */
    public function sortReport(array $report) : array
    {
        return $report;
    }

}