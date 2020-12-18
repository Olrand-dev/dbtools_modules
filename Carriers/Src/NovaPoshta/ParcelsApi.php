<?php

declare(strict_types=1);

namespace NutixApp\Carriers\Src\NovaPoshta;


use NutixApp\Core\Src\Utils\DateHelper;
use NutixApp\Orders\Orders;
use NutixApp\Core\Src\Db\NPDO;
use NutixApp\Core\Src\App;
use NutixApp\Orders\Src\OrdersController;

class ParcelsApi extends ApiProfile 
{
    /**
     * Данные получателей из заказов в нашей базе
     * @var array
     */
    public $recipientDataStacks;

    /**
     * Данные о посылках, полученные от API Новой Почты
     * @var array
     */
    public $parcelsData;

    
    public function __construct()
    {
        $this->model = 'TrackingDocument';
        $this->method = 'getStatusDocuments';

        parent::__construct();
    }


    public function handleParcelsData() : void 
    {
        $orders = NPDO::$models->orders;
        $ordersItems = NPDO::$models->ordersItems;
        $tasks = NPDO::$models->tasks;
        $codPayment = Orders::ORDERS_PAYMENT_TYPE_COD;

        $pending = 1; //посылка ожидается на отделении отправителя
        $parcelRecStatus = [9, 10, 11]; //посылка получена клиентом
        $recReject = [102, 103, 108]; //отказ клиента
        $inPostOffice = [7, 8]; //посылка в отделении получателя
        $shipping = [4, 41, 5, 6, 7, 8, 9, 10, 11]; //посылка отправлена

        $statusToReturn = Orders::ORDER_STATUS_TORETURN;
        $statusPerformed = Orders::ORDER_STATUS_PERFORMED;
        $statusShipping = Orders::ORDER_STATUS_SHIPPING;
        $statusAssembled = Orders::ORDER_STATUS_ASSEMBLED;

        /* $this->parcelsData[] = [
            'parcel' => [
                'StatusCode' => 7,
                'DatePayedKeeping' => '2018-12-14 00:00:00',
            ],
            'order' => [
                'id' => 629,
            ]
        ]; */

        foreach ($this->parcelsData as $parcelData) {
            if (count($parcelData['order']) === 0) continue;
            
            $id = (int) $parcelData['order']['id'];
            $statusCode = (int) $parcelData['parcel']['StatusCode'];
            $orderOtherData = json_decode(
                (string) $orders->val('other_data', '`id` = ?', [$id]), true
            );
            $orderPending = $orderOtherData['pending'] ?? 0;

            if ($parcelData['order']['status_id'] === $statusShipping) {

                if ($statusCode === $pending) {               
                    $orderOtherData['pending'] = 1;
                    $orders->update(['other_data' => json_encode($orderOtherData)], $id);
                }

                if ($statusCode > $pending and (int) $orderPending === 1) {
                    $orderOtherData['pending'] = 0;
                    $orders->update(['other_data' => json_encode($orderOtherData)], $id);
                }
    
                //if (in_array($statusCode, $inPostOffice)) {
    
                    if (!empty($parcelData['parcel']['DateFirstDayStorage'])) {
                        
                        $startStorageDate = DateHelper::getUnixFromDate($parcelData['parcel']['DateFirstDayStorage'], 'Y-m-d');
                        $storageDays = floor((time() - $startStorageDate) / DateHelper::DAY);
                        if ($storageDays >= 3 and $parcelData['order']['by_ordering'] === 1) {
                            $tasks->byOrderingParcelStorage3Days($id);
                        }
                    }
    
                    if (!empty($parcelData['parcel']['DatePayedKeeping'])) {
                        
                        $payedKeepingDate = DateHelper::getUnixFromDate($parcelData['parcel']['DatePayedKeeping'], 'Y-m-d H:i:s');
                        $now = DateHelper::now();
                        if (($payedKeepingDate - $now) < (DateHelper::DAY * 2)) {
                            $tasks->parcelStorage2DaysToPayedKeeping($id);
                        }
                    }
                //}

                if (in_array($statusCode, $recReject)) {
                    
                    OrdersController::changeOrderStatus($id, $statusToReturn, false);
                }
    
                if (in_array($statusCode, $parcelRecStatus)) {
                    
                    OrdersController::changeOrderStatus($id, $statusPerformed, false);
                }
    
                if (isset($parcelData['order']['pid']) and $parcelData['order']['pid'] === $codPayment) {

                    $orderCod = $orders->val('cod_payment', '`id` = ?', [$id]);
                    $parcelCod = (float) $parcelData['parcel']['RedeliverySum'];
                    
                    $diff = ($orderCod < $parcelCod) ?
                        round($parcelCod - $orderCod, 2) :
                        round($orderCod - $parcelCod, 2);
                    if ($diff > 0) $orders->update(['cod_p_diff' => $diff], $id);

                    App::$cache->clear(Orders::CACHE_TYPE_ORDER, (string) $id);
                    NPDO::$models->ordersStatistics->updateStat($id, [
                        'need_update' => 1,
                    ]);
                }
            }


            if ($parcelData['order']['status_id'] === $statusAssembled) {

                if (in_array($statusCode, $shipping)) {
                    
                    OrdersController::changeOrderStatus($id, $statusShipping, false);
                }
            }
        }
    }


    public function getParcelsData() : void 
    {

        $parcelsDataAll = [];

        for ($i = 0; $i < count($this->recipientDataStacks); $i++) {
            $data = [];

            foreach ($this->recipientDataStacks[$i] as $recipientData) {
                $data[] = 
                "{\r\n
                    \"DocumentNumber\": \"{$recipientData['track_code']}\",\r\n
                    \"Phone\": \"{$recipientData['phone']}\"\r\n
                \r\n}";
            }

            $data = implode(",\r\n", $data);
            $properties = "{\r\n \"Documents\": [\r\n" . $data . "\r\n] \r\n}";

            $parcelsData = $this->getData($properties);

            foreach ($parcelsData as $parcelData) {
                $recipientData = [];

                foreach ($this->recipientDataStacks[$i] as $data) {
                    if ($parcelData['Number'] === $data['track_code']) {
                        $recipientData = $data;
                        break;
                    }
                }

                $parcelsDataAll[] = [
                    'parcel' => $parcelData,
                    'order' => $recipientData,
                ];
            }
        }
        
        $this->parcelsData = $parcelsDataAll;
        //var_dump($this->parcelsData);exit;
    }


    public function getRecipientsData() : void 
    {
        /**
         * Размер стека запроса - о скольких получателях можно получить информацию за один запрос
         */
        $requestStackSize = 90;

        $orders = NPDO::$models->orders;

        $novaPoshta = Orders::ORDERS_DELIVERY_TYPE_NOVA_POSHTA;
        $statusShipping = Orders::ORDER_STATUS_SHIPPING;
        $statusAssembled = Orders::ORDER_STATUS_ASSEMBLED;
        $codPayment = Orders::ORDERS_PAYMENT_TYPE_COD;

        $data = $orders->rows(
            "SELECT `id`, `pid`, `track_code`, `phone`, `notif_codes`, `status_id`, `by_ordering` FROM %table% 
            WHERE `did` = ? AND (`pid` != ? OR `pid` IS NULL) 
            AND (`status_id` = ? OR `status_id` = ?) AND `track_code` != ''", 
        [$novaPoshta, $codPayment, $statusShipping, $statusAssembled]);

        $data = array_merge($data, $orders->rows(
            "SELECT `id`, `pid`, `track_code`, `phone`, `notif_codes`, `status_id`, `by_ordering` FROM %table% 
            WHERE `did` = ? AND `pid` = ? AND `cod_p_confirmed` = 0 AND 
            (`status_id` = ? OR `status_id` = ?) AND `track_code` != ''", 
        [$novaPoshta, $codPayment, $statusShipping, $statusAssembled]));

        /*$data[] = ['id' => '12498', 'track_code' => '20450264739929', 'phone' => '+380668621008'];
        $data[] = ['id' => '12542', 'track_code' => '20450265576579', 'phone' => '+380501096402'];*/

        $recipientsNum = count($data);
        $stacks = [];

        if ($recipientsNum > $requestStackSize) {

            $stacksNum = ceil($recipientsNum / $requestStackSize);

            for ($i = 0; $i < $stacksNum; $i++) {
                $startIndex = $i * $requestStackSize;
                $stack = array_slice($data, $startIndex, $requestStackSize);

                foreach ($stack as &$orderData) {
                    $orderData['track_code'] = trim($orderData['track_code']);
                } unset($orderData);

                $stacks[] = $stack;
            }

        } else {
            $stacks[] = $data;
        }

        $_stacks = [];
        foreach ($stacks as $stack) {
            $_stack = [];
            foreach ($stack as $orderData) {
                $orderData['track_code'] = trim($orderData['track_code']);
                $orderData['track_code'] = str_replace(' ', '', $orderData['track_code']);
                $_stack[] = $orderData;
            }
            $_stacks[] = $_stack;
        }
        $stacks = $_stacks;

        $this->recipientDataStacks = $stacks;
    }

}