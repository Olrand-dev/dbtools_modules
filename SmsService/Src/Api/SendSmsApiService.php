<?php

declare(strict_types=1);

namespace NutixApp\SmsService\Src\Api;


use NutixApp\Core\Src\Http\ApiService;
use NutixApp\Core\Src\Http\HttpHandlerInterface;
use NutixApp\Core\Src\App;
use NutixApp\SmsService\Src\SmsClubApi;
use NutixApp\Core\Src\Db\NPDO;

class SendSmsApiService extends ApiService implements HttpHandlerInterface 
{

    public $alias = 'send-sms';

    public $module = 'sms-service';

    public $route = 'api/sms-service/send-sms';

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
        $to = App::$requestData['phone'];
        $message = App::$requestData['message'];
        $addData = App::$requestData['add_data'];
        $smsData = [
            'source' => $addData['source'] ?? '',
            'source_id' => $addData['source_id'] ?? 0,
        ];
        $templateId = (int) App::$requestData['template_id'];
        $smsData['sms_alias'] = NPDO::$models->smsTemplates->val(
            'alias',
            '`id` = ?',
            [$templateId]
        );

        if (!empty(App::$session->storedSmsMessage)) {
            $message = App::$session->storedSmsMessage;
            App::$session->storedSmsMessage = '';
        }
        
        $api = new SmsClubApi();
        self::$data = [
            'sms_ids' => $api->sendSms($to, $message, $smsData),
            'phone' => $to,
            'message' => $message,
        ];
        self::sendResponse();
    }

}