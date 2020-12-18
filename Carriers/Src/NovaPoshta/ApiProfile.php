<?php

declare(strict_types=1);

namespace NutixApp\Carriers\Src\NovaPoshta;

use NutixApp\Core\Src\HTTPClient;
use NutixApp\Carriers\Src\NovaPoshta\Catalogs\CargoTypesCatalogApi;
use NutixApp\Carriers\Src\NovaPoshta\Catalogs\PayerTypesCatalogApi;
use NutixApp\Carriers\Src\NovaPoshta\Catalogs\PaymentMethodsCatalogApi;
use NutixApp\Carriers\Src\NovaPoshta\Catalogs\ServiceTypesCatalogApi;
use NutixApp\Carriers\Src\NovaPoshta\Catalogs\SearchSettlementsCatalogApi;
use NutixApp\Carriers\Src\NovaPoshta\Catalogs\SearchStreetsCatalogApi;
use NutixApp\Core\Src\Exception\NutixException;

class ApiProfile extends HTTPClient
{
    public const HTTP_METHOD = 'post';

    public const API_URL_JSON = 'https://api.novaposhta.ua/v2.0/json/';

    public const SENDER = 'aada3655-f5f8-11e6-8ba8-005056881c6b';

    public const CONTACT_SENDER = 'c0140f9d-cc8c-11e8-8b24-005056881c6b';

    public const SENDER_CITY_DEFAULT = '8d5a980d-391c-11dd-90d9-001a92567626'; //Киев

    public const SENDER_ADDRESS_DEFAULT = '324fb8fc-b7f4-11e8-ad0d-005056b24375'; //отделение 298

    public const SENDER_PHONE_DEFAULT = '380958087177';

    public const COD_PAYER_TYPES = [
        [
            'id' => 'Sender',
            'name' => 'Відправник',
        ],
        [
            'id' => 'Recipient',
            'name' => 'Одержувач',
        ],
    ];

    /**
     * @var string
     */
    protected $model;

    /**
     * @var string
     */
    protected $method;

    /**
     * @var string
     */
    protected $apiKey = 'f29d03fc4e0e93557bb3af2870a19389';

    /**
     * @var bool
     */
    protected $newAddress = true;

    /**
     * @var array
     */
    protected $payerTypes;

    /**
     * @var array
     */
    protected $paymentMethods;

    /**
     * @var array
     */
    protected $cargoTypes;

    /**
     * @var array
     */
    protected $serviceTypes;

    /**
     * @var array
     */
    protected $citiesList;

    /**
     * @var array
     */
    protected $streetsList;


    public function __construct()
    {
        parent::__construct();

        $this->url = self::API_URL_JSON;
        $this->headers = [
            'Content-Type' => 'string',
        ];
    }


    /**
     * @return array
     * @throws NutixException
     */
    protected function getData(string $properties) : array 
    {
        $this->body =
        "{\r\n
            \"apiKey\": \"{$this->apiKey}\",\r\n
            \"modelName\": \"{$this->model}\",\r\n
            \"calledMethod\": \"{$this->method}\",\r\n
            \"methodProperties\": $properties
        \r\n}";

        $response = json_decode($this->getResponse(self::HTTP_METHOD, false), true);
        if (empty($response)) return [];

        if ($response['success']) {
            return $response['data'];
        } else {
            throw new NutixException('get data error', $response);
        }
    }


    /**
     * Собрать массив данных справочников API для html селекторов
     * 
     * @param string[] $types Список алиасов необходимых типов данных
     * 
     * @return array
     */
    public function getCatalogsData(array $types) : array 
    {
        $options = [];

        foreach ($types as $type) {
            $method = 'get' . ucfirst($type);

            if (method_exists($this, $method)) {
                $this->$method();
                if (!empty($this->$type)) $options[$type] = $this->convertToOptions($this->$type);
                else $options[$type] = [];
            }
        }
        return $options;
    }


    /**
     * @param array $apiData
     * @return array
     */
    private function convertToOptions(array $apiData) : array
    {
        return array_map(function($optionData) {
            return [
                'id' => $optionData['Ref'],
                'name' => $optionData['Description'],
            ];
        }, $apiData);
    }


    protected function getPayerTypes() : void
    {
        $api = new PayerTypesCatalogApi();
        $this->payerTypes = $api->getCatalogData();
    }


    protected function getPaymentMethods() : void
    {
        $api = new PaymentMethodsCatalogApi();
        $this->paymentMethods = $api->getCatalogData();
    }


    protected function getCargoTypes() : void 
    {
        $api = new CargoTypesCatalogApi();
        $this->cargoTypes = $api->getCatalogData();
    }


    protected function getServiceTypes() : void 
    {
        $api = new ServiceTypesCatalogApi();
        $this->serviceTypes = $api->getCatalogData();
    }


    protected function searchSettlements(string $cityName) : void 
    {
        $api = new SearchSettlementsCatalogApi();
        $this->citiesList = $api->getCatalogData($cityName);
    }


    protected function searchStreets(string $streetName, string $cityRef) : void 
    {
        $api = new SearchStreetsCatalogApi();
        $this->streetsList = $api->getCatalogData($streetName, $cityRef);
    }

}