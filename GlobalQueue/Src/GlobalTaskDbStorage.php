<?php

declare(strict_types=1);

namespace NutixApp\GlobalQueue\Src;


use NutixApp\Core\Src\Db\NPDO;
use NutixApp\Core\Src\Exception\NutixException;
use NutixApp\Core\Src\Utils\DateHelper;
use NutixApp\Core\Src\Utils\NumHelper;
use NutixApp\GlobalQueue\GlobalQueue;

abstract class GlobalTaskDbStorage extends GlobalTask
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
     * @var int
     */
    public $dataLength;
    
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
     * @var string
     */
    public $comment;

    /**
     * @var int[]
     */
    protected $taskDataTypes = [];

    public const DATA_STORAGE_TYPE = parent::TASK_DATA_STORAGE_TYPE_DB;

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
        'comment' => 'comment',
        'create_time' => 'createDatetime',
        'update_time' => 'updateDatetime',
        'finish_time' => 'finishDatetime',
    ];

    public const TASK_DATA_TYPE_MAIN = 1;
    public const TASK_DATA_TYPE_TEMP = 2;
    public const TASK_DATA_TYPE_REPORT = 3;

    protected const DATA_TYPE_TABLE_SUFFIXES = [
        self::TASK_DATA_TYPE_MAIN => 'main',
        self::TASK_DATA_TYPE_TEMP => 'temp',
        self::TASK_DATA_TYPE_REPORT => 'report',
    ];

    public const TASK_DATA_TABLE_FIELDS = [
        'id' => 'int',
        'name' => 'string',
        'marker' => 'string',
        'is_json' => 'bool_int',
        'value' => 'text',
    ];


    /**
     * Main data
     */
    protected function saveMainData(array $data) : void {}
    public abstract function getDataChunk(int $offset, int $limit) : array;


    /**
     * Temp data
     */
    public function saveTempData(array $data) : void {}
    protected function getTempData() : array
    {
        return [];
    }

    /**
     * Report data
     */
    public function saveReportData(array $data) : void {}
    protected function getReportData() : array
    {
        return [];
    }


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
    public function __construct(int $id)
    {
        $this->id = $id;
        $this->status = GlobalQueue::TASK_STATUS_NEW;
        $this->cursor = 0;
        $this->percCompleted = 0;
        $this->finished = 0;
        $this->comment = '';
        $this->dataLength = 0;

        if (empty($this->id)) {

            $this->id = NPDO::$models->globalTasks->insert([]);
            $this->initTempTables();

        } else {

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

            if ($data['finished'] !== 1) {
                $this->dataLength = $this->getDataLength();
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
        }
    }


    public function saveValue(int $type, $val, string $name = '', string $marker = '') : void
    {
        $isJson = is_array($val);
        if ($isJson) $val = json_encode($val);
        $suffix = $this->getTaskTempTableSuffix($type);

        NPDO::$models->globalTasks->tempInsert([
            'name' => $name,
            'marker' => $marker,
            'is_json' => ($isJson) ? 1 : 0,
            'value' => $val,
        ], true, $suffix);
    }


    public function readValues(int $type, string $marker = '', int $offset = 0, int $limit = 0) : array
    {
        NPDO::connect('gt');
        NPDO::$models->globalTasks->setFieldsMap(
            self::TASK_DATA_TABLE_FIELDS
        );
        NPDO::$handleDataActionsSettings['html_entity_decode'] = false;

        $suffix = $this->getTaskTempTableSuffix($type);
        $sql = "SELECT * FROM %table% WHERE 1";
        $bindings = [];

        if (!empty($marker)) {
            $sql .= " AND `marker` LIKE ?";
            $bindings[] = $marker;
        }
        $sql .= " ORDER BY `id` ASC";
        if ($offset > 0 or $limit > 0) {
            $sql .= " LIMIT " . (($offset > 0) ? "$offset," : "") . $limit;
        }

        $data = [];
        $dbData = NPDO::$models->globalTasks->tempRows($sql, $bindings, $suffix);
        foreach ($dbData as $row) {
            $val = $row['value'];
            if ($row['is_json'] === 1) {
                $val = json_decode($val, true);
            }
            $name = $row['name'];
            $_marker = $row['marker'];

            if (!empty($_marker)) {

                if (!empty($name)) {
                    $data[$_marker][$name] = $val;
                } else {
                    $data[$_marker][] = $val;
                }
            } else if (!empty($name)) {

                $data[$name] = $val;
            } else {
                $data[] = $val;
            }
        }

        NPDO::connect(MAIN_DB_CONNECTION);
        NPDO::$models->globalTasks->resetFieldsMap();
        NPDO::$handleDataActionsSettings['html_entity_decode'] = true;
        return $data;
    }


    public function readValue(int $type, string $name)
    {
        NPDO::connect('gt');
        NPDO::$models->globalTasks->setFieldsMap(
            self::TASK_DATA_TABLE_FIELDS
        );

        $suffix = $this->getTaskTempTableSuffix($type);
        $rows = NPDO::$models->globalTasks->tempRows(
            "SELECT `value`, `is_json` FROM %table% WHERE `name` LIKE ?",
            [$name],
            $suffix
        );
        if (count($rows) > 0) {
            $row = $rows[0];
            $val = $row['value'];
            if ($row['is_json'] === 1) $val = json_decode($val, true);
        } else $val = null;

        NPDO::connect(MAIN_DB_CONNECTION);
        NPDO::$models->globalTasks->resetFieldsMap();
        return $val;
    }


    protected function getTaskTempTableSuffix(int $type) : string
    {
        return "_{$this->id}_" . self::DATA_TYPE_TABLE_SUFFIXES[$type];
    }


    public function initTempTables() : void
    {
        NPDO::connect('gt');

        foreach ($this->taskDataTypes as $dataType) {

            NPDO::$models->globalTasks->initTempTableIfNotExists(
                $this->getTaskTempTableSuffix($dataType),
                self::TASK_DATA_TABLE_FIELDS
            );
        }
        NPDO::connect(MAIN_DB_CONNECTION);
    }


    public function deleteTempTables() : void
    {
        NPDO::connect('gt');

        foreach ($this->taskDataTypes as $dataType) {

            NPDO::$models->globalTasks->deleteTempTableIfExists(
                $this->getTaskTempTableSuffix($dataType)
            );
        }
        NPDO::connect(MAIN_DB_CONNECTION);
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

        $this->save();
    }


    public function save(bool $onlyInfo = false) : void
    {
        $this->calculatePercCompleted();

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
        $perc = (int) NumHelper::getPercents($this->dataLength, $this->cursor, 0);
        if ($perc > 100) $perc = 100;
        $this->percCompleted = $perc;
    }
    
}