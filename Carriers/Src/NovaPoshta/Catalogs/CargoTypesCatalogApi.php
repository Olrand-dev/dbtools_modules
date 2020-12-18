<?php

declare(strict_types=1);

namespace NutixApp\Carriers\Src\NovaPoshta\Catalogs;

use NutixApp\Carriers\Src\NovaPoshta\ApiProfile;

class CargoTypesCatalogApi extends ApiProfile
{
    
    private $excludes = [
        'Documents',
    ];


    public function __construct()
    {
        $this->model = 'Common';
        $this->method = 'getCargoTypes';

        parent::__construct();
    }


    /**
     * @return array
     */
    public function getCatalogData() : array
    {
        $properties = '{}';
        $data = $this->getData($properties);
        if (empty($data)) return [];
        
        return array_filter($data, function($item) {
            return !in_array($item['Ref'], $this->excludes);
        });
    }

}