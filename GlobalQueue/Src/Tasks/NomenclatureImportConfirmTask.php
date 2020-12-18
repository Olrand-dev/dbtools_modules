<?php

declare(strict_types=1);

namespace NutixApp\GlobalQueue\Src\Tasks;

use NutixApp\Core\Src\App;
use NutixApp\GlobalQueue\GlobalQueue;
use NutixApp\GlobalQueue\Src\GlobalTaskFileStorage;
use NutixApp\GlobalQueue\Src\GlobalTasksController;
use NutixApp\NomenclatureImport\Src\NomenclatureImportController;

class NomenclatureImportConfirmTask extends GlobalTaskFileStorage
{


    public function __construct(int $id = 0, bool $onlyInfo = true)
    {
        $this->type = GlobalQueue::TASK_TYPE_NOMENCLATURE_IMPORT_CONFIRM;
        $this->name = 'Подтверждение импорта номенклатуры';
        $this->description = '';
        $this->priority = 9;

        parent::__construct($id, $onlyInfo);

        $this->limit = 5000;
    }


    public function getDataChunk(int $offset, int $limit) : array
    {
        return [
            'data' => array_slice($this->data['data'], $offset, $limit),
            'doubled_articles' => $this->data['doubled_articles'],
        ];
    }


    public function getDataLength() : int 
    {
        return count($this->data['data']);
    }


    public function onFailed() : void
    {
        $importType = GlobalTasksController::getTaskImportType($this->type, (int) $this->code);

        $confirmWaiting = App::$storage->get('prod_import_confirm_waiting');
        $confirmWaiting[$importType] = 3;
        App::$storage->set('prod_import_confirm_waiting', $confirmWaiting);
    }


    public function before(array $args = []) : void 
    {

    }


    public function after(array $args = []) : bool 
    {
        $importType = $args['import_type'];
        $contrId = (int) $this->code;
        $images = $this->tempResultData['prod_images'];

        return NomenclatureImportController::finishImportConfirmation(
            $importType, $contrId, $images
        );
    }
    
}