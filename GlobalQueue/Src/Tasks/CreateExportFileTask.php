<?php

declare(strict_types=1);

namespace NutixApp\GlobalQueue\Src\Tasks;

use NutixApp\Core\Src\App;
use NutixApp\Core\Src\Db\NPDO;
use NutixApp\Core\Src\File\File;
use NutixApp\Core\Src\Utils\DateHelper;
use NutixApp\Export\Src\ExportController;
use NutixApp\GlobalQueue\GlobalQueue;
use NutixApp\GlobalQueue\Src\GlobalTaskDbStorage;

class CreateExportFileTask extends GlobalTaskDbStorage
{

    protected $taskDataTypes = [
        GlobalTaskDbStorage::TASK_DATA_TYPE_MAIN,
        GlobalTaskDbStorage::TASK_DATA_TYPE_TEMP,
    ];


    public function __construct(int $id = 0)
    {
        $this->type = GlobalQueue::TASK_TYPE_CREATE_EXPORT_FILE;
        $this->name = 'Генерация файла экспорта';
        $this->description = '';
        $this->priority = 6;

        parent::__construct($id);

        $this->limit = 5000;
    }


    public function onDelete(): void
    {
        NPDO::$models->export->execute(
            "UPDATE %table% SET `export_file_status` = 0 WHERE `id` = ?",
            [(int) $this->code]
        );
    }


    protected function saveMainData(array $data) : void
    {
        NPDO::connect('gt');
        NPDO::$models->globalTasks->setFieldsMap(
            GlobalTaskDbStorage::TASK_DATA_TABLE_FIELDS
        );
        $type = GlobalTaskDbStorage::TASK_DATA_TYPE_MAIN;

        foreach ($data['products'] as $productData) {
            $this->saveValue($type, $productData, '', 'products');
        }
        foreach ($data['categories'] as $catData) {
            $this->saveValue($type, $catData, '', 'categories');
        }

        $this->saveValue($type, $data['templates'], 'templates');
        $this->saveValue($type, $data['currency'], 'currency');

        NPDO::connect(MAIN_DB_CONNECTION);
        NPDO::$models->globalTasks->resetFieldsMap();
    }


    public function getDataChunk(int $offset, int $limit) : array
    {
        $data = $this->readValues(
            GlobalTaskDbStorage::TASK_DATA_TYPE_MAIN,
            'products',
            $offset,
            $limit
        )['products'];

        return $data;
    }


    public function saveTempData(array $data) : void
    {
        NPDO::connect('gt');
        NPDO::$models->globalTasks->setFieldsMap(
            GlobalTaskDbStorage::TASK_DATA_TABLE_FIELDS
        );
        $type = GlobalTaskDbStorage::TASK_DATA_TYPE_TEMP;

        if (isset($data['products'])) {
            foreach ($data['products'] as $productData) {

                $this->saveValue($type, $productData, '', 'products');
            }
        }

        if (isset($data['categories'])) {
            foreach ($data['categories'] as $alias => $catData) {

                $this->saveValue($type, $catData, (string) $alias, 'categories');
            }
        }

        foreach (['name', 'company', 'url'] as $alias) {
            if (isset($data[$alias])) {
                $this->saveValue($type, $data[$alias], $alias);
            }
        }

        NPDO::connect(MAIN_DB_CONNECTION);
        NPDO::$models->globalTasks->resetFieldsMap();
    }


    protected function getTempData(): array
    {
        return $this->readValues(GlobalTaskDbStorage::TASK_DATA_TYPE_TEMP);
    }


    public function getDataLength() : int 
    {
        NPDO::connect('gt');

        $table = NPDO::$models->globalTasks->getTempName(
            $this->getTaskTempTableSuffix(GlobalTaskDbStorage::TASK_DATA_TYPE_MAIN)
        );
        $len = NPDO::$models->globalTasks->getCountFiltered(
            '`marker` LIKE ?',
            ['products'],
            $table
        );

        NPDO::connect(MAIN_DB_CONNECTION);
        return $len;
    }


    public function before(array $args = []) : void 
    {
        $id = $args['id'];
        $targetAlias = $args['alias'];

        App::$session->productsConnectionsTargetId = $id;
        
        ExportController::initExportProfile($targetAlias);

        $folderPath = ExportController::getPath('export_result_folder', $targetAlias);
        File::make('folder', $folderPath);

        $data = ExportController::$profile->getExportData();
        $this->saveMainData($data);

        $tempResultData = [];
        $categoriesData = ExportController::$profile->getCategoriesData($data);

        $tempResultData['categories'] = $categoriesData;
        $tempResultData = array_merge($tempResultData, ExportController::$profile->customData);
        $this->saveTempData($tempResultData);

        $beginXmlTemplate = $data['templates']['begin'];
        $beginXmlData = array_merge(
            ExportController::$profile->customData,
            [
                'date' =>  DateHelper::dateFormated(DateHelper::now(), 'd-m-Y H:i'),
                'currency' => $data['currency'],
                'categories' => $categoriesData,
            ]
        );
        $beginXmlResultData = App::fetchView($beginXmlTemplate, ['data' => $beginXmlData]);
        $exportFilePath = ExportController::getPath('export_result_file', $targetAlias);
        file_put_contents($exportFilePath, $beginXmlResultData);
    }


    public function after(array $args = []) : bool 
    {
        $alias = NPDO::$models->export->val('alias', '`id` = ?', [(int) $this->code]);
        $exportFilePath = ExportController::getPath('export_result_file', $alias);

        $type = GlobalTaskDbStorage::TASK_DATA_TYPE_MAIN;
        $template = $this->readValue($type, 'templates')['end'];

        $resultData = App::fetchView($template);

        $result = file_put_contents($exportFilePath, $resultData, FILE_APPEND | LOCK_EX);
        if (!$result) {
            return false;
        }

        NPDO::$models->export->execute(
            "UPDATE %table% SET `export_file_status` = 1 WHERE `alias` LIKE ?",
            [$alias]
        );
        return true;
    }
    
}