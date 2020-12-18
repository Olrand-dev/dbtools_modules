<?php

declare(strict_types=1);

namespace NutixApp\GlobalQueue\Src\Tasks;

use NutixApp\Core\Src\File\File;
use NutixApp\Core\Src\Module;
use NutixApp\Core\Src\Utils\DateHelper;
use NutixApp\GlobalQueue\GlobalQueue;
use NutixApp\GlobalQueue\Src\GlobalTaskFileStorage;
use NutixApp\NomenclatureImport\Src\NomenclatureControlFileController;

class CreateNomenclatureControlFileTask extends GlobalTaskFileStorage
{


    public function __construct(int $id = 0, bool $onlyInfo = true)
    {
        $this->type = GlobalQueue::TASK_TYPE_CREATE_NOMENCLATURE_CONTROL_FILE;
        $this->name = 'Создание файла выгрузки номенклатуры';
        $this->description = '';
        $this->priority = 5;

        parent::__construct($id, $onlyInfo);

        $this->limit = 4000;
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
        $filePath = NomenclatureControlFileController::getControlFilePath(
            $args['import_type'], $args['contr_alias']
        );
        if (file_exists($filePath)) File::delete($filePath);
    }


    public function after(array $args = []) : bool 
    {
        $filePath = NomenclatureControlFileController::getControlFilePath(
            $args['import_type'], $args['contr_alias']
        );

        File::init('excel');
        File::setFilePath($filePath);
        File::write($this->tempResultData, [
            'for_download' => true,
        ]);
        return true;
    }
    
}