<?php

declare(strict_types=1);

namespace NutixApp\GlobalQueue\Src;


use NutixApp\Core\Src\Db\NPDO;
use NutixApp\Core\Src\Exception\NutixException;
use NutixApp\Core\Src\File\File;
use NutixApp\Core\Src\Module;
use NutixApp\Core\Src\Utils\DateHelper;
use NutixApp\Core\Src\Utils\NumHelper;
use NutixApp\GlobalQueue\GlobalQueue;

abstract class GlobalTaskFileStorage extends GlobalTask
{

    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $description;

    /**
     * @var string
     */
    public $type;

    /**
     * @var string
     */
    public $code;

    /**
     * @var string
     */
    public $status;

    /**
     * @var int
     */
    public $priority;

    /**
     * @var string
     */
    protected $dir;

    /**
     * @var array
     */
    public $data;

    /**
     * @var array
     */
    public $tempResultData;

    /**
     * @var int
     */
    public $cursor;

    /**
     * @var int
     */
    public $limit;

    /**
     * @var int
     */
    public $percCompleted;

    /**
     * @var bool
     */
    public $finished;

    /**
     * @var string
     */
    public $finishDatetime;

    /**
     * @var int
     */
    public $filesRemoved;

    /**
     * @var string
     */
    public $comment;

    public const DATA_STORAGE_TYPE = parent::TASK_DATA_STORAGE_TYPE_FILE;

    private const FIELDS_MAP = [
        'id' => 'id',
        'name' => 'name',
        'desc' => 'description',
        'type' => 'type',
        'code' => 'code',
        'status' => 'status',
        'priority' => 'priority',
        'cursor' => 'cursor',
        'perc_compl' => 'percCompleted',
        'finished' => 'finished',
        'files_removed' => 'filesRemoved',
        'comment' => 'comment',
        'create_time' => 'createDatetime',
        'update_time' => 'updateDatetime',
        'finish_time' => 'finishDatetime',
    ];


    /**
     * @return array
     */
    public abstract function getDataChunk(int $offset, int $limit) : array;


    public abstract function getDataLength() : int;


    /**
     * Подготовка перед началом выполнения задачи
     */
    public abstract function before(array $args = []) : void;


    /**
     * Действия после завершения выполнения задачи, нужно проверять вернулось ли true из данного
     * метода перед тем как переводить задачу в выполненные
     */
    public abstract function after(array $args = []) : bool;


    /**
     * @throws NutixException
     */
    public function __construct(int $id, bool $onlyInfo = true)
    {
        $this->id = $id;
        $this->dir = Module::getModuleFilesRoot('global-queue') . DS . $this->type;
        $this->status = GlobalQueue::TASK_STATUS_NEW;
        $this->cursor = 0;
        $this->percCompleted = 0;
        $this->finished = 0;
        $this->comment = '';
        $this->data = [];
        $this->tempResultData = [];

        if (!empty($this->id)) {

            $data = NPDO::$models->globalTasks->row(
                "SELECT * FROM %table% WHERE `type` LIKE ? AND `id` = ?",
                [$this->type, $this->id]
            );

            if (empty($data)) {
                throw new NutixException('empty task data', [
                    'type' => $this->type,
                    'id' => $this->id,
                ]);
            }

            foreach ($data as $field => $value) {

                if (!in_array($field, array_keys(self::FIELDS_MAP))) continue;
                $_field = self::FIELDS_MAP[$field];

                switch ($_field) {

                    case 'finished': {
                        $value = $value === 1;
                        break;
                    }
                    case 'createDatetime':
                    case 'updateDatetime':
                    case 'finishDatetime': {
                        $value = (!empty($value)) ? DateHelper::dateFormated($value, 'd-m-Y, H:i:s') : '';
                        break;
                    }
                }

                $this->$_field = $value;
            }

            if (!$onlyInfo) {
                $this->data = $this->readTaskData($this->getDataFilePath());
                $this->tempResultData = $this->readTaskData($this->getTempResultDataFilePath());
            }
        }
    }


    public function getFieldsData() : array
    {
        $data = [];
        foreach ($this as $key => $value) {
            $data[$key] = $value;
        }
        return $data;
    }


    public function __toString()
    {
        return json_encode($this->getFieldsData());
    }


    public function update(array $data) : void
    {
        $this->priority = $data['priority'];
        $this->comment = $data['comment'];

        $this->save(true);
    }


    public function save(bool $onlyInfo = false) : void
    {
        if (empty($this->id)) {
            $this->id = NPDO::$models->globalTasks->insert([]);
        }

        if (empty($this->data)) $this->data = [];
        if (!$onlyInfo) {
            $this->calculatePercCompleted();

            $this->saveTaskData($this->data, $this->getDataFilePath());
            if (!empty($this->tempResultData)) {
                $this->saveTaskData($this->tempResultData, $this->getTempResultDataFilePath());
            }
        }

        $data = $this->getFieldsData();
        $fieldsMap = array_flip(self::FIELDS_MAP);
        $_data = [];

        foreach ($data as $field => $value) {

            if (!in_array($field, array_keys($fieldsMap))) continue;

            switch ($field) {
                case 'finished': {
                    $value = ($value) ? 1 : 0;
                    break;
                }
                case 'createDatetime':
                case 'updateDatetime':
                case 'finishDatetime': {
                    $value = (!empty($value)) ?
                        DateHelper::getUnixFromDate($value, 'd-m-Y, H:i:s') : null;
                    break;
                }
            }

            $_field = $fieldsMap[$field];
            $_data[$_field] = $value;
        }

        NPDO::$models->globalTasks->update($_data, $this->id);
    }


    private function calculatePercCompleted() : void
    {
        $dataLength = $this->getDataLength();
        $perc = (int) NumHelper::getPercents($dataLength, $this->cursor, 0);
        if ($perc > 100) $perc = 100;
        $this->percCompleted = $perc;
    }


    protected function getDataFilePath() : string
    {
        return $this->dir . DS . $this->id . '.txt';
    }


    protected function getTempResultDataFilePath() : string
    {
        return $this->dir . DS . $this->id . '_result_temp.txt';
    }


    protected function getReportFilePath(string $alias) : string
    {
        return $this->dir . DS . 'reports' . DS . $alias . '_report.txt';
    }


    public function removeTaskFiles(bool $clearFilesFolder = false) : void
    {
        if ($clearFilesFolder) {

            File::clearFolder($this->dir);

        } else {

            $files = [
                $this->getDataFilePath(),
                $this->getTempResultDataFilePath(),
            ];

            foreach ($files as $filePath) {

                unlink($filePath);

            }
        }
    }


    public function saveReport(array $reportData, string $reportAlias) : void
    {
        $path = $this->getReportFilePath($reportAlias);
        File::saveDataToFile(json_encode($reportData), $path);
    }


    public function readReport(string $reportAlias) : array
    {
        $data = [];

        $path = $this->getReportFilePath($reportAlias);
        if (file_exists($path)) {
            $data = File::readDataFromFile($path);
            $data = json_decode($data, true);
        }
        return $data;
    }


    /**
     * @param array $data
     * @param string $path
     */
    protected function saveTaskData(array $data, string $path) : void
    {
        $data = json_encode($data);
        File::saveDataToFile($data, $path);
    }


    protected function readTaskData(string $path) : array
    {
        if (!file_exists($path)) {
            $data = [];
            $this->saveTaskData($data, $path);
        } else {
            $data = File::readDataFromFile($path);
            $data = (array) json_decode($data, true);
        }
        return $data;
    }

}