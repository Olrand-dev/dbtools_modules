<?php

declare(strict_types=1);

namespace NutixApp\Carriers\Src\Api;


use NutixApp\Carriers\Src\NovaPoshta\TrackingCodeApi;
use NutixApp\Core\Src\App;
use NutixApp\Core\Src\Db\NPDO;
use NutixApp\Core\Src\Http\ApiService;
use NutixApp\Core\Src\Http\HttpHandlerInterface;
use NutixApp\Orders\Src\OrdersController;

class NPGetTrackCodeApiService extends ApiService implements HttpHandlerInterface 
{

    public $alias = 'np-get-track-code';

    public $module = 'carriers';

    public $route = 'api/carriers/np/get-track-code';

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
        $formData = App::$requestData['form_data'];
        $codPayment = App::$requestData['cod_payment'] == 1;
        $orderId = (int) App::$requestData['order_id'];

        $api = new TrackingCodeApi();
        self::$data = $api->getTrackCode($formData, $codPayment);

        NPDO::$models->orders->update(
            ['tc_stored_data' => json_encode($formData)], $orderId
        );
        OrdersController::clearOrderCache($orderId);
        self::sendResponse();
    }

}