<?php

declare(strict_types=1);

namespace NutixApp\Carriers\Src\Api;

use NutixApp\Carriers\Src\NovaPoshta\Catalogs\CityWarehousesCatalogApi;
use NutixApp\Core\Src\App;
use NutixApp\Core\Src\Http\ApiService;
use NutixApp\Core\Src\Http\HttpHandlerInterface;

class NPGetApiWarehousesListApiService extends ApiService implements HttpHandlerInterface 
{

    public $alias = 'np-get-api-warehouses-list';

    public $module = 'carriers';

    public $route = 'api/carriers/np/get-api-warehouses-list';

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
        $api = new CityWarehousesCatalogApi();
        $cityRef = App::$requestData['city_ref'];

        $results = $api->getCatalogData($cityRef);

        if (empty($results)) self::$data = [];
        else self::$data = array_map(function($whData) {
            return [
                'id' => $whData['Number'],
                'name' => $whData['Description'],
            ];
        }, $results);

        self::sendResponse();
    }

}