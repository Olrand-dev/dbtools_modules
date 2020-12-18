<?php

declare(strict_types=1);

namespace NutixApp\GlobalQueue\Src\Tasks;


use NutixApp\Core\Src\App;
use NutixApp\Core\Src\Exception\NutixException;
use NutixApp\GlobalQueue\GlobalQueue;
use NutixApp\GlobalQueue\Src\GlobalTaskFileStorage;

class UpdateStoresCacheFilesTask extends GlobalTaskFileStorage
{


    public function __construct(int $id = 0, bool $onlyInfo = true)
    {
        $this->type = GlobalQueue::TASK_TYPE_UPDATE_STORES_CACHE_FILES;
        $this->name = 'Обновление файлов кэша магазинов';
        $this->description = '';
        $this->priority = 2;

        parent::__construct($id, $onlyInfo);

        $this->limit = 5000;
    }


    public function getDataChunk(int $offset, int $limit) : array
    {
        return $this->data;
    }


    public function getDataLength() : int 
    {
        $len =  $this->tempResultData['length'] ?? 0;
        return (int) $len;
    }


    /**
     * @throws NutixException
     */
    public function before(array $args = []) : void 
    {

    }


    public function after(array $args = []) : bool 
    {
        App::$storage->set('update-stores-cache-files-tasks-list', null);
        return true;
    }
    
}