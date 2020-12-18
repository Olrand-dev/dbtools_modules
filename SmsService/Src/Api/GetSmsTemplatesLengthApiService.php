<?php

declare(strict_types=1);

namespace NutixApp\SmsService\Src\Api;


use NutixApp\Core\Src\Http\ApiService;
use NutixApp\Core\Src\Http\HttpHandlerInterface;
use NutixApp\Core\Src\Db\NPDO;

class GetSmsTemplatesLengthApiService extends ApiService implements HttpHandlerInterface 
{

    public $alias = 'get-sms-templates-length';

    public $module = 'sms-service';

    public $route = 'api/sms-service/get-sms-templates-length';

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
        
        NPDO::$models->smsTemplates->addCustomFields(['length' => 'int']);
        $row = NPDO::$models->smsTemplates->row(
            "SELECT COUNT(*) AS `length` FROM %table%"
        );
        self::$data = $row['length'];
        self::sendResponse();
    }

}