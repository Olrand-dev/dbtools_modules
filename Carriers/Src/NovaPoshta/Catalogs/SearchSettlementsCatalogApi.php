<?php

declare(strict_types=1);

namespace NutixApp\Carriers\Src\NovaPoshta\Catalogs;

use NutixApp\Carriers\Src\NovaPoshta\ApiProfile;

class SearchSettlementsCatalogApi extends ApiProfile
{
    
    private $limit = 30;


    public function __construct()
    {
        $this->model = 'Address';
        $this->method = 'searchSettlements';

        parent::__construct();
    }


    /**
     * @return array
     */
    public function getCatalogData(string $cityName) : array
    {
        $properties = "{\"CityName\": \"{$cityName}\", \"Limit\": {$this->limit}}";
        return $this->getData($properties);
    }

}