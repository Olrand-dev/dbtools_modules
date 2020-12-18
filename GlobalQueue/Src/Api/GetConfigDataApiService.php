<?php

declare(strict_types=1);

namespace NutixApp\GlobalQueue\Src\Api;


use NutixApp\Core\Src\Http\ApiService;
use NutixApp\Core\Src\Http\HttpHandlerInterface;
use NutixApp\Core\Src\Utils\ArrayHelper;
use NutixApp\GlobalQueue\GlobalQueue;

class GetConfigDataApiService extends ApiService implements HttpHandlerInterface 
{

    public $alias = 'get-config-data';

    public $module = 'global-queue';

    public $route = 'api/global-queue/get-config-data';

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
        
        self::$data = [
            'task_types' => ArrayHelper::assocArrToHtmlSelectData(GlobalQueue::TASK_TYPE_NAMES),
            'task_type_default' => GlobalQueue::TASK_TYPE_DEFAULT,
            'task_statuses' => ArrayHelper::assocArrToHtmlSelectData(GlobalQueue::TASK_STATUS_NAMES),
            'task_status_default' => GlobalQueue::TASK_STATUS_DEFAULT,
            'sort_types' => ArrayHelper::assocArrToHtmlSelectData(GlobalQueue::SORT_TYPE_NAMES),
            'sort_type_default' => GlobalQueue::SORT_TYPE_DEFAULT,
        ];
        self::sendResponse();
    }

}