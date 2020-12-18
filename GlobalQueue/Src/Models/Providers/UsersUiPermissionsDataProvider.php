<?php

declare(strict_types=1);

namespace NutixApp\GlobalQueue\Src\Models\Providers;


use NutixApp\Core\Src\Db\DbDataProviderInterface;

class UsersUiPermissionsDataProvider implements DbDataProviderInterface 
{

    public const TARGET_TABLE = 'usersuipermissions';

    
    public static function getData() : array 
    {

        return [
            [
                'code' => 'yyt',
                'section' => 'global-queue',
                'name' => 'Системная очередь - окно',
                'admin' => 1,
                'investor' => 0,
                'manager' => 0,
                'courier' => 0,
                'warehouseman' => 0,
            ], 
        ];
    }
    
}