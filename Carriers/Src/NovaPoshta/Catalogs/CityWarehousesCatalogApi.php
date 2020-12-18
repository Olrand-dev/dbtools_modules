<?php

declare(strict_types=1);

namespace NutixApp\Carriers\Src\NovaPoshta\Catalogs;

use NutixApp\Carriers\Src\NovaPoshta\ApiProfile;

class CityWarehousesCatalogApi extends ApiProfile
{


    public function __construct()
    {
        $this->model = 'Address';
        $this->method = 'getWarehouses';

        parent::__construct();
    }


    /**
     * @return array
     */
    public function getCatalogData(string $cityRef) : array
    {
        $properties = "{\"SettlementRef\": \"{$cityRef}\"}";
        return $this->getData($properties);
    }

}