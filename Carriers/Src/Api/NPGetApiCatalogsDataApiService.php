<?php

declare(strict_types=1);

namespace NutixApp\Carriers\Src\Api;

use NutixApp\Carriers\Src\NovaPoshta\TrackingCodeApi;
use NutixApp\Core\Src\Http\ApiService;
use NutixApp\Core\Src\Http\HttpHandlerInterface;

class NPGetApiCatalogsDataApiService extends ApiService implements HttpHandlerInterface 
{

    public $alias = 'np-get-api-catalogs-data';

    public $module = 'carriers';

    public $route = 'api/carriers/np/get-api-catalogs-data';

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
        $api = new TrackingCodeApi();

        $data = $api->getCatalogsData([
            'payerTypes',
            'paymentMethods',
            'cargoTypes',
            'serviceTypes',
        ]);
        $data['codPayerTypes'] = $api::COD_PAYER_TYPES;

        self::$data = $data;
        self::sendResponse();
    }

}