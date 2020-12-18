<?php

declare(strict_types=1);

namespace NutixApp\SmsService\Src\Api;


use NutixApp\Core\Src\Http\ApiService;
use NutixApp\Core\Src\Http\HttpHandlerInterface;
use NutixApp\Core\Src\App;
use NutixApp\SmsService\Src\SmsClubApi;

class GetSmsMessageApiService extends ApiService implements HttpHandlerInterface 
{

    public $alias = 'get-sms-message';

    public $module = 'sms-service';

    public $route = 'api/sms-service/get-sms-message';

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
        $refData = App::$requestData['ref_data'];
        $templateId = (int) App::$requestData['template_id'];

        $api = new SmsClubApi();
        self::$data = $api->getSmsMessage($refData, $templateId);
        self::sendResponse();
    }

}