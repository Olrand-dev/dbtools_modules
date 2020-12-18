<?php

declare(strict_types=1);

namespace NutixApp\GlobalQueue\Src\Tasks;

use NutixApp\GlobalQueue\GlobalQueue;
use NutixApp\GlobalQueue\Src\GlobalTaskFileStorage;

class ImportImagesTask extends GlobalTaskFileStorage
{


    public function __construct(int $id = 0, bool $onlyInfo = true)
    {
        $this->type = GlobalQueue::TASK_TYPE_IMPORT_PROD_IMAGES;
        $this->name = 'Импорт фото товаров';
        $this->description = '';
        $this->priority = 1;

        parent::__construct($id, $onlyInfo);

        $this->limit = 750;
    }


    public function getDataChunk(int $offset, int $limit) : array
    {
        return array_slice($this->data, $offset, $limit);
    }


    public function getDataLength() : int 
    {
        return (is_array($this->data)) ? count($this->data) : 0;
    }


    public function before(array $args = []) : void 
    {

    }


    public function after(array $args = []) : bool 
    {
        return true;
    }
    
}