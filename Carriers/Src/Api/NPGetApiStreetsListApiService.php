<?php

declare(strict_types=1);

namespace NutixApp\Carriers\Src\Api;

use NutixApp\Carriers\Src\NovaPoshta\Catalogs\SearchStreetsCatalogApi;
use NutixApp\Core\Src\App;
use NutixApp\Core\Src\Http\ApiService;
use NutixApp\Core\Src\Http\HttpHandlerInterface;

class NPGetApiStreetsListApiService extends ApiService implements HttpHandlerInterface 
{

    public $alias = 'np-get-api-streets-list';

    public $module = 'carriers';

    public $route = 'api/carriers/np/get-api-streets-list';

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
        $api = new SearchStreetsCatalogApi();
        $streetNameSearch = App::$requestData['name'];
        $cityRef = App::$requestData['city_ref'];

        $results = $api->getCatalogData($streetNameSearch, $cityRef);

        if (empty($results)) self::$data = [];
        else self::$data = array_map(function($streetData) {
            return [
                'id' => $streetData['Ref'],
                'name' => $streetData['Present'],
                'value' => $streetData['Present'],
            ];
        }, $results[0]['Addresses']);

        self::sendResponse();
    }

}