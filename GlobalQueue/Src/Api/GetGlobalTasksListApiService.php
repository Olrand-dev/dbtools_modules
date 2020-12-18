<?php

declare(strict_types=1);

namespace NutixApp\GlobalQueue\Src\Api;


use NutixApp\Core\Src\App;
use NutixApp\Core\Src\Http\ApiService;
use NutixApp\Core\Src\Http\HttpHandlerInterface;
use NutixApp\GlobalQueue\Src\GlobalTasksController;

class GetGlobalTasksListApiService extends ApiService implements HttpHandlerInterface 
{

    public $alias = 'get-global-tasks-list';

    public $module = 'global-queue';

    public $route = 'api/global-queue/get-global-tasks-list';

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
        $type = App::$requestData['type'];
        $status = App::$requestData['status'];
        $sort = App::$requestData['sort'];
        $page = (int) App::$requestData['page'];
        $perPage = (int) App::$requestData['per_page'];
        
        self::$data = [
            'pagination_config' => GlobalTasksController::getPaginationData($type, $status, $perPage),
            'tasks_list' => GlobalTasksController::getList($type, $status, $sort, $page, $perPage),
        ];
        self::sendResponse();
    }

}