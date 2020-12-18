<?php

declare(strict_types=1);

namespace NutixApp\Carriers\Src\NovaPoshta\Catalogs;

use NutixApp\Carriers\Src\NovaPoshta\ApiProfile;

class SearchStreetsCatalogApi extends ApiProfile
{
    
    private $limit = 30;


    public function __construct()
    {
        $this->model = 'Address';
        $this->method = 'searchSettlementStreets';

        parent::__construct();
    }


    /**
     * @return array
     */
    public function getCatalogData(string $streetName, string $cityRef) : array
    {
        $properties = "{\"StreetName\": \"{$streetName}\", \"SettlementRef\": \"{$cityRef}\",
            \"Limit\": {$this->limit}}";
        return $this->getData($properties);
    }

}