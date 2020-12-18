<?php

declare(strict_types=1);

namespace NutixApp\Carriers\Src\Api;

use NutixApp\Carriers\Src\NovaPoshta\Catalogs\SearchSettlementsCatalogApi;
use NutixApp\Core\Src\App;
use NutixApp\Core\Src\Http\ApiService;
use NutixApp\Core\Src\Http\HttpHandlerInterface;

class NPGetApiSettlementsListApiService extends ApiService implements HttpHandlerInterface 
{

    public $alias = 'np-get-api-cities-list';

    public $module = 'carriers';

    public $route = 'api/carriers/np/get-api-cities-list';

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
        $api = new SearchSettlementsCatalogApi();
        $cityNameSearch = App::$requestData['name'];

        $results = $api->getCatalogData($cityNameSearch);
        
        if (empty($results)) self::$data = [];
        else self::$data = array_map(function($cityData) {
            return [
                'id' => $cityData['Ref'],
                'name' => $cityData['Present'],
                'value' => $cityData['MainDescription'],
                'add_data' => [
                    'area' => $cityData['Area'],
                    'region' => $cityData['Region'],
                ],
            ];
        }, $results[0]['Addresses']);

        self::sendResponse();
    }

}