<?php

declare(strict_types=1);

namespace NutixApp\Carriers\Src\NovaPoshta;

use NutixApp\Core\Src\Utils\DateHelper;

class TrackingCodeApi extends ApiProfile
{

    private $trackCodeFormMap = [
        'payer_type' => 'PayerType',
        'payment_method' => 'PaymentMethod',
        'cargo_type' => 'CargoType',
        'service_type' => 'ServiceType',
        'volume_general' => 'VolumeGeneral',
        'weight' => 'Weight',
        'seats_amount' => 'SeatsAmount',
        'cost' => 'Cost',
        'desc' => 'Description',
        'rec_cityname' => 'RecipientCityName',
        'rec_area' => 'RecipientArea',
        'rec_region' => 'RecipientAreaRegions',
        'rec_address' => 'RecipientAddressName',
        'rec_house' => 'RecipientHouse',
        'rec_flat' => 'RecipientFlat',
        'rec_name' => 'RecipientName',
        'rec_phone' => 'RecipientsPhone',
        'delivery_date' => 'DateTime',
        'cod_payer' => 'CodPayerType',
        'cod_amount' => 'CodRedeliveryString',
    ];


    public function __construct()
    {
        $this->model = 'InternetDocument';
        $this->method = 'save';

        parent::__construct();
    }


    public function getTrackCode(array $formData, bool $codPayment = false) : string 
    {
        $data = $this->convertTrackCodeFormKeys($formData);
        $data['NewAddress'] = ($this->newAddress) ? '1' : '0';
        $data['CitySender'] = self::SENDER_CITY_DEFAULT;
        $data['Sender'] = self::SENDER;
        $data['SenderAddress'] = self::SENDER_ADDRESS_DEFAULT;
        $data['ContactSender'] = self::CONTACT_SENDER;
        $data['SendersPhone'] = self::SENDER_PHONE_DEFAULT;
        $data['RecipientType'] = 'PrivatePerson';
        $data['DateTime'] = DateHelper::dateFormated(
            DateHelper::getUnixFromDate($data['DateTime']), 'd.m.Y'
        );

        if ($codPayment) {
            $codData = [
                'PayerType' => $data['CodPayerType'],
                'CargoType' => 'Money',
                'RedeliveryString' => $data['CodRedeliveryString'],
            ];
            $codProperties = $this->prepareToJson($codData);

        }
        unset($data['cod_payer'], $data['cod_amount']);

        $properties = $this->prepareToJson($data);
        $propertiesStr = '{';
        $propertiesStr .= $properties;
        if (isset($codProperties)) {
            $propertiesStr .= ", \"BackwardDeliveryData\": [{{$codProperties}}]";
        }
        $propertiesStr .= '}';

        $result = $this->getData($propertiesStr);
        if (empty($result)) return '';
        
        $code = $result[0]['IntDocNumber'] ?? '';
        return (string) $code;
    }


    private function convertTrackCodeFormKeys(array $rawFormData) : array
    {
        $converted = [];

        foreach ($rawFormData as $key => $value) {
            if (in_array($key, array_keys($this->trackCodeFormMap))) {
                $converted[$this->trackCodeFormMap[$key]] = $value;
            }
        }
        return $converted;
    }


    private function prepareToJson(array $data) : string 
    {
        $pairs = [];
        foreach ($data as $key => $value) {
            $pairs[] = "\"$key\": \"$value\"";
        }
        return implode(', ', $pairs);
    }

}