<?php

declare(strict_types=1);

namespace NutixApp\GlobalQueue\Src\Tasks;

use NutixApp\Core\Src\App;
use NutixApp\Core\Src\Db\NPDO;
use NutixApp\GlobalQueue\GlobalQueue;
use NutixApp\GlobalQueue\Src\GlobalTaskFileStorage;
use NutixApp\NomenclatureImport\NomenclatureImport;
use NutixApp\NomenclatureImport\Src\NomenclatureImportController;

class ImportNomenclatureControlFileTask extends GlobalTaskFileStorage
{


    public function __construct(int $id = 0, bool $onlyInfo = true)
    {
        $this->type = GlobalQueue::TASK_TYPE_IMPORT_NOMENCLATURE_CONTROL_FILE;
        $this->name = 'Импорт файла выгрузки номенклатуры';
        $this->description = '';
        $this->priority = 4;

        parent::__construct($id, $onlyInfo);

        $this->limit = 3000;
    }


    public function getDataChunk(int $offset, int $limit) : array
    {
        return array_slice($this->data, $offset, $limit);
    }


    public function getDataLength() : int 
    {
        return (is_array($this->data)) ? count($this->data) : 0;
    }


    public function sortReport(array $report): array
    {
        NomenclatureImportController::sortImportReport($report);
        return $report;
    }


    public function before(array $args = []) : void 
    {
        App::$storage->set(NomenclatureImport::IMPORTED_DATA_STORAGE_ALIAS, null);
        App::$storage->set(NomenclatureImport::IMPORT_DOUBLE_ARTICLES_STORAGE_ALIAS, null);
        App::$storage->set('nomenclature_import_has_new_prods', 0);

        NPDO::$models->products->deleteTempTableIfExists();
        NPDO::$models->contractorsProducts->deleteTempTableIfExists();
        NPDO::$models->texts->deleteTempTableIfExists();

        $importType = $args['import_type'];
        if (
            $importType === 'contractor_control_file' or
            $importType === 'our_control_file'
        ) {
            App::$storage->set(
                NomenclatureImport::IMPORT_CONTRACTOR_PRODS_ID, 
                $args['contractor_id']
            );
        }
        
        $confirmWaiting = App::$storage->get('prod_import_confirm_waiting') ?? [
            'our_control_file' => 0,
            'contractor_products' => 0,
            'contractor_control_file' => 0,
        ];
        $confirmWaiting[$importType] = 0;
        App::$storage->set('prod_import_confirm_waiting', $confirmWaiting);

        $reportAlias = ($importType === 'our_control_file') ? $importType :
            "{$importType}_{$args['contractor_id']}";
        $this->saveReport([], $reportAlias);
    }


    public function after(array $args = []) : bool 
    {
        $confirmWaiting = App::$storage->get('prod_import_confirm_waiting');
        $confirmWaiting[$args['import_type']] = 1;
        App::$storage->set('prod_import_confirm_waiting', $confirmWaiting);

        return true;
    }
    
}