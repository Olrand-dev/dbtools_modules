<?php

declare(strict_types=1);

namespace NutixApp\SmsService\Src\Api;


use NutixApp\Core\Src\Http\ApiService;
use NutixApp\Core\Src\Http\HttpHandlerInterface;
use NutixApp\Core\Src\Db\NPDO;

class GetSmsTemplatesListApiService extends ApiService implements HttpHandlerInterface 
{

    public $alias = 'get-sms-templates-list';

    public $module = 'sms-service';

    public $route = 'api/sms-service/get-sms-templates-list';

    public $needAuth = true;

    public $permissionsMap = [
        'admin' => 1,
        'investor' => 0,
        'manager' => 1,
        'courier' => 0,
        'warehouseman' => 0,
    ];


    public function run() : void 
    {
        self::$data = NPDO::$models->smsTemplates->all();
        self::sendResponse();
    }

}