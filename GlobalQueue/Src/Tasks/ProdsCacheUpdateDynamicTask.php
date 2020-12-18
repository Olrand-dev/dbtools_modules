<?php

declare(strict_types=1);

namespace NutixApp\GlobalQueue\Src\Tasks;

use NutixApp\GlobalQueue\GlobalQueue;
use NutixApp\GlobalQueue\Src\GlobalTaskFileStorage;

class ProdsCacheUpdateDynamicTask extends GlobalTaskFileStorage
{


    public function __construct(int $id = 0, bool $onlyInfo = true)
    {
        $this->type = GlobalQueue::TASK_TYPE_UPDATE_PRODS_CACHE_DYNAMIC;
        $this->name = 'Обновление кэша товаров - динам.';
        $this->description = '';
        $this->priority = 7;
        
        parent::__construct($id, $onlyInfo);

        $this->limit = 6000;
    }


    public function getDataChunk(int $offset, int $limit) : array
    {
        return array_slice($this->data, $offset, $limit);
    }


    public function getDataLength() : int 
    {
        return count($this->data);
    }


    public function before(array $args = []) : void 
    {

    }


    public function after(array $args = []) : bool 
    {
        return true;
    }
    
}