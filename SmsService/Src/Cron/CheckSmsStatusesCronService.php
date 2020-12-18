<?php

declare(strict_types=1);

namespace NutixApp\SmsService\Src\Cron;


use NutixApp\Core\Src\Http\CronService;
use NutixApp\Core\Src\Http\HttpHandlerInterface;
use NutixApp\SmsService\Src\SmsClubApi;
use NutixApp\Core\Src\Event\SystemEvent;

class CheckSmsStatusesCronService extends CronService implements HttpHandlerInterface 
{

    public $alias = 'check-sms-statuses';

    public $module = 'sms-service';

    public $route = 'cron/external-api/check-sms-statuses';


    public function run() : void 
    {
        
        $api = new SmsClubApi;
        SystemEvent::trigger('SmsStatusesChecked', ['sms_data_list' => $api->checkSmsStatuses()]);
    }

}