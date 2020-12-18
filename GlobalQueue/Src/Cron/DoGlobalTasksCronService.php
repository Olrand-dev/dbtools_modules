<?php

declare(strict_types=1);

namespace NutixApp\GlobalQueue\Src\Cron;


use NutixApp\Core\Src\App;
use NutixApp\Core\Src\Db\NPDO;
use NutixApp\Core\Src\Event\SystemEvent;
use NutixApp\Core\Src\Exception\NutixException;
use NutixApp\Core\Src\Http\CronService;
use NutixApp\Core\Src\Http\HttpHandlerInterface;
use NutixApp\GlobalQueue\Src\GlobalTasksController;

class DoGlobalTasksCronService extends CronService implements HttpHandlerInterface 
{

    public $alias = 'do-global-tasks';

    public $module = 'global-queue';

    public $route = 'cron/global-queue/do-global-tasks';


    public function run() : void 
    {
        GlobalTasksController::deleteFinishedTaskFiles();
        App::$session->importNomenclatureForceAddNewProds = false;
        App::$session->importNomenclatureNotOverwriteMode = false;
        
        $taskId = GlobalTasksController::getNextTaskId();
        if (empty($taskId)) return;

        App::$storage->set('global_task_at_work', $taskId);

        $taskData = NPDO::$models->globalTasks->row(
            "SELECT * FROM %table% WHERE `id` = ?", [$taskId]
        );

        try {

            SystemEvent::trigger('DoGlobalTask', [
                'type' => $taskData['type'],
                'code' => $taskData['code'],
                'id' => $taskId,
            ]);

        } catch(\Exception $e) {

            if ($e instanceof NutixException) {
                $details = $e->getDetails();
            } else {
                $details = [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ];
            }

            GlobalTasksController::moveTaskToFailed($taskId, $details);
        }
    }

}