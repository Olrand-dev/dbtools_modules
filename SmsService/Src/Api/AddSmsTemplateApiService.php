<?php

declare(strict_types=1);

namespace NutixApp\SmsService\Src\Api;


use NutixApp\Core\Src\Http\ApiService;
use NutixApp\Core\Src\Http\HttpHandlerInterface;
use NutixApp\Core\Src\Db\NPDO;
use NutixApp\Core\Src\App;

class AddSmsTemplateApiService extends ApiService implements HttpHandlerInterface 
{

    public $alias = 'add-sms-template';

    public $module = 'sms-service';

    public $route = 'api/sms-service/add-sms-template';

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
        $data = App::$requestData['row'];
        
        self::$data = NPDO::$models->smsTemplates->insert($data);
        self::sendResponse();
    }

}