<?php

declare(strict_types=1);

namespace NutixApp\Carriers\Src\Justin;


use NutixApp\Orders\Orders;
use NutixApp\Core\Src\Db\NPDO;
use NutixApp\Orders\Src\OrdersController;

class ParcelsApi 
{


    public function handleParcelsData() : void
    {
        $justin = Orders::ORDERS_DELIVERY_TYPE_JUSTIN;
        $statusAssembled = Orders::ORDER_STATUS_ASSEMBLED;
        $statusShipping = Orders::ORDER_STATUS_SHIPPING;
        $statusPerformed = Orders::ORDER_STATUS_PERFORMED;

        $orders = NPDO::$models->orders->rows(
            "SELECT `id`, `status_id` FROM %table% WHERE `did` = ? AND 
            (`status_id` = ? OR `status_id` = ?) AND `track_code` != ''",
            [$justin, $statusAssembled, $statusShipping]
        ); 
        
        foreach ($orders as $orderData) {
            $id = $orderData['id'];

            switch ($orderData['status_id']) {

                case $statusAssembled: {
                    OrdersController::changeOrderStatus($id, $statusShipping, false);
                    break;
                }
                case $statusShipping: {
                    OrdersController::changeOrderStatus($id, $statusPerformed, false);
                    break;
                }
            }
        }
    }

}