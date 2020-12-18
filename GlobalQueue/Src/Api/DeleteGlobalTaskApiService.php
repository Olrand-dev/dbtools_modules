<?php

declare(strict_types=1);

namespace NutixApp\GlobalQueue\Src\Api;


use NutixApp\Core\Src\App;
use NutixApp\Core\Src\Http\ApiService;
use NutixApp\Core\Src\Http\HttpHandlerInterface;
use NutixApp\GlobalQueue\Src\TaskRunner;

class DeleteGlobalTaskApiService extends ApiService implements HttpHandlerInterface 
{

    public $alias = 'delete-global-task';

    public $module = 'global-queue';

    public $route = 'api/global-queue/delete-global-task';

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

        $runner = new TaskRunner($id);
        $runner->deleteTask();
        self::sendResponse();
    }

}