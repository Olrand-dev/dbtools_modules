<?php

declare(strict_types=1);

namespace NutixApp\SmsService\Src\Api;


use NutixApp\Core\Src\Http\ApiService;
use NutixApp\Core\Src\Http\HttpHandlerInterface;
use NutixApp\Core\Src\Db\NPDO;
use NutixApp\Core\Src\App;

class DeleteSmsTemplateApiService extends ApiService implements HttpHandlerInterface 
{

    public $alias = 'delete-sms-template';

    public $module = 'sms-service';

    public $route = 'api/sms-service/delete-sms-template';

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
        $id = (int) App::$requestData['id'];
        
        NPDO::$models->smsTemplates->deleteRow($id);
        self::sendResponse();
    }

}