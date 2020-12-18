<?php

declare(strict_types=1);

namespace NutixApp\Carriers\Src\NovaPoshta\Catalogs;

use NutixApp\Carriers\Src\NovaPoshta\ApiProfile;

class PaymentMethodsCatalogApi extends ApiProfile
{

    
    public function __construct()
    {
        $this->model = 'Common';
        $this->method = 'getPaymentForms';

        parent::__construct();
    }


    /**
     * @return array
     */
    public function getCatalogData() : array
    {
        $properties = '{}';
        return $this->getData($properties);
    }

}