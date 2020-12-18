<?php

declare(strict_types=1);

namespace NutixApp\GlobalQueue\Src\Tasks;

use NutixApp\Contractors\Src\ContractorsController;
use NutixApp\Core\Src\App;
use NutixApp\Core\Src\Db\NPDO;
use NutixApp\GlobalQueue\GlobalQueue;
use NutixApp\GlobalQueue\Src\GlobalTaskFileStorage;
use NutixApp\NomenclatureImport\NomenclatureImport;
use NutixApp\NomenclatureImport\Src\NomenclatureImportController;

class ImportContractorProdsTask extends GlobalTaskFileStorage
{


    public function __construct(int $id = 0, bool $onlyInfo = true)
    {
        $this->type = GlobalQueue::TASK_TYPE_IMPORT_CONTRACTOR_PRODS;
        $this->name = 'Импорт товаров поставщика';
        $this->description = '';
        $this->priority = 8;

        parent::__construct($id, $onlyInfo);

        $this->limit = 5000;
    }


    public function getDataChunk(int $offset, int $limit) : array
    {
        $data = [];
        $arr = $this->data['data'] ?? [];

        if (!empty($arr)) {
            $data = array_slice($arr, $offset, $limit);
        }
        return $data;
    }


    public function getDataLength() : int 
    {
        return (is_array($this->data['data'])) ? count($this->data['data']) : 0;
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
        App::$storage->set('nomenclature_import_inactive_prod_ids', null);
        App::$storage->set('contractors_import_outstock_prod_ids', null);
        App::$storage->set('nomenclature_import_has_new_prods', 0);

        App::$storage->set(
            NomenclatureImport::IMPORT_CONTRACTOR_PRODS_ID, 
            $args['contractor_id']
        );

        $reportPath = $this->getReportFilePath($args['report_alias']);
        if (file_exists($reportPath)) unlink($reportPath);

        NPDO::$models->contractorsProducts->deleteTempTableIfExists();
        NPDO::$models->texts->deleteTempTableIfExists();

        $confirmWaiting = App::$storage->get('prod_import_confirm_waiting') ?? [
            'our_control_file' => 0,
            'contractor_products' => 0,
            'contractor_control_file' => 0,
        ];
        $confirmWaiting['contractor_products'] = 0;
        App::$storage->set('prod_import_confirm_waiting', $confirmWaiting);

        $this->saveReport([], $args['report_alias']);
    }


    public function after(array $args = []) : bool 
    {
        $confirmWaiting = App::$storage->get('prod_import_confirm_waiting');
        $confirmWaiting['contractor_products'] = 1;
        App::$storage->set('prod_import_confirm_waiting', $confirmWaiting);
        $contractorId = (int) $this->code;
        $allCodes = $this->tempResultData['all_codes'];

        /**
         * Найти товары, которых нету в текущем прайсе поставщика,
         * при подтверждении импорта они станут не в наличии
         */
        $missingProdIds = ContractorsController::getMissingProdIds($contractorId, $allCodes);
        $type = 'contractors_import_outstock_prod_ids';
        $storedIds = (array) App::$storage->get($type);
        $ids = array_merge($storedIds, $missingProdIds);
        App::$storage->set($type, $ids);

        if ($args['auto_confirm']) {

            $this->tempResultData['auto_confirm_needed'] = 1;
        }
        return true;
    }
    
}