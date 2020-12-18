<?php

declare(strict_types=1);

namespace NutixApp\GlobalQueue\Src\Api;


use NutixApp\Core\Src\App;
use NutixApp\Core\Src\Http\ApiService;
use NutixApp\Core\Src\Http\HttpHandlerInterface;
use NutixApp\GlobalQueue\GlobalQueue;

class SaveGlobalTaskApiService extends ApiService implements HttpHandlerInterface 
{

    public $alias = 'save-global-task';

    public $module = 'global-queue';

    public $route = 'api/global-queue/save-global-task';

    public $needAuth = true;

    public $permissionsMap = [
        'admin' => 1,
        'investor' => 0,
        'manager' => 0,
        'courier' => 0,
        'warehouseman' => 0,
    ];


    public function run() : void 
    {
        $id = (int) App::$requestData['id'];
        $data = App::$requestData['data'];

        $taskClass = GlobalQueue::TASKS_MAP[$data['type']];
        $task = new $taskClass($id);
        $task->update($data);
        
        self::sendResponse();
    }

}