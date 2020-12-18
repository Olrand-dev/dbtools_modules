<?php

declare(strict_types=1);

namespace NutixApp\SmsService\Src\Api;

use NutixApp\Core\Src\App;
use NutixApp\Core\Src\Http\ApiService;
use NutixApp\Core\Src\Http\HttpHandlerInterface;
use NutixApp\Core\Src\Db\NPDO;

class GetSmsTemplatesPageApiService extends ApiService implements HttpHandlerInterface 
{

    public $alias = 'get-sms-templates-page';

    public $module = 'sms-service';

    public $route = 'api/sms-service/get-sms-templates-page';

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
        $offset = App::$requestData['offset']; 
        $limit = App::$requestData['limit']; 
        
        self::$data = NPDO::$models->smsTemplates->rows(
            "SELECT * FROM %table% ORDER BY `id` LIMIT $offset,$limit"
        );
        self::sendResponse();
    }

}