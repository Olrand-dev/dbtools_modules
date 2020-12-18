<?php

declare(strict_types=1);

namespace NutixApp\SmsService;

abstract class SmsService 
{

    public static $name = 'API';

    public static $alias = 'sms-service';

    public static $assets = [];

    public static $dependencies = [
        'orders',
        'users',
        'tasks',
    ];

    public static $sessionDataMap = [
        'storedSmsMessage' => 'string',
    ];

    public const SMS_VARIABLE_CARD_NUM = '&card_num';
    
}