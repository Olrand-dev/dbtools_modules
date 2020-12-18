<?php

declare(strict_types=1);

namespace NutixApp\SmsService\Src\Api;


use NutixApp\Core\Src\Http\ApiService;
use NutixApp\Core\Src\Http\HttpHandlerInterface;
use NutixApp\SmsService\Src\SmsClubApi;

class GetSmsBalanceApiService extends ApiService implements HttpHandlerInterface 
{

    public $alias = 'get-sms-balance';

    public $module = 'sms-service';

    public $route = 'api/sms-service/get-sms-balance';

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
        $api = new SmsClubApi;

        $balance = $api->getBalance();
        if (!empty($balance)) $balance = explode('<br/>', $balance)[0];
        else $balance = 0;

        self::$data = round((float) $balance, 2);
        self::sendResponse();
    }

}