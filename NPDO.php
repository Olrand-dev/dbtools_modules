<?php

declare(strict_types=1);

namespace NutixApp\Core\Src\Db;


use NutixApp\Core\Core;
use NutixApp\Core\Src\App;
use NutixApp\Core\Src\Exception\NutixException;
use NutixApp\Core\Src\File\File;
use NutixApp\Core\Src\ModelStack;
use NutixApp\Core\Src\Utils\DateHelper;
use NutixApp\Core\Src\Utils\StringHelper;
use NutixApp\Users\Users;

/**
 * Class NPDO
 * @package NutixApp\Core\Src\Db
 */
class NPDO 
{

    /**
     * @var string
     */
    public $tableName;

    /**
     * @var ModelStack
     */
    public static $models;

    /**
     * @var bool
     */
    public static $debugMode;

    public static $handleDataActionsSettings = [
        'trim' => true,
        'html_entity_decode' => true,
    ];

    /**
     * @var bool
     */
    public static $addSystemData = true;

    /**
     * Карта полей таблицы с их типами
     * @var string[]
     */
    private $fieldsMap;

    /**
     * @var string[]
     */
    private $defaultFieldsMap;

    /**
     * Список индексов таблицы
     * @var array
     */
    private $indexes;

    /**
     * @var string
     */
    private static $connectionName;

    /**
     * @var \PDO
     */
    private static $pdo;

    /**
     * @var \PDO[]
     */
    private static $pdoStack = [];

    /**
     * @var string
     */
    private static $dbName;

    /**
     * @var string
     */
    private static $dbType;

    /**
     * @var bool
     */
    private static $dbConnected = false;

    /**
     * @var array
     */
    private static $debugLog = [];

    public const TEMP_TABLE_SUFFIX = '_temp';


    public function __construct() 
    {
        if (
            !self::tableExists($this->tableName) and
            self::$dbConnected and
            self::$connectionName === MAIN_DB_CONNECTION
        ) {
            $this->initTableStructure();
            $this->createIndexes();
        }
    }


    public static function checkConnectionName(string $name) : bool
    {
        return $name === self::$connectionName;
    }


    /**
     * Подключиться к базе данных согласно переданной конфигурации
     * @throws NutixException
     */
    public static function connect(string $connectAlias) : void
    {
        $connectData = DB_CONNECTION[$connectAlias] ?? [];
        if (empty($connectData)) {
            throw new NutixException('no DB connection data by this alias', [
                'connect_alias' => $connectAlias,
            ]);
        }

        if (self::$dbConnected) self::$dbConnected = false;

        self::$connectionName = $connectAlias;

        if (!empty(self::$pdoStack[$connectAlias])) {
            self::$pdo = self::$pdoStack[$connectAlias];
        } else {

            switch($connectData['db_type']) {

                case 'sqlite': {

                    $dbFile = SQLITE_DB_DIR . DS . $connectData['base'] . '.sqlite';

                    try {

                        self::$pdo = new \PDO("sqlite:$dbFile");

                    } catch (\PDOException $e) {
                        throw new NutixException('SQLite db setup error', [], $e);
                    }
                    break;
                }

                case 'pgsql':
                case 'mysql': {

                    $type = $connectData['db_type'];
                    $host = $connectData['host'];
                    $port = $connectData['port'] ?? 3306;
                    $base = $connectData['base'];
                    $user = $connectData['user'];
                    $pass = $connectData['pass'];
                    $charset = $connectData['charset'] ?? 'utf8';

                    $dsn = "$type:host=$host;port=$port;dbname=$base;charset=$charset";
                    $options = [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                        \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
                    ];

                    try {

                        self::$pdo = new \PDO($dsn, $user, $pass, $options);

                    } catch (\PDOException $e) {
                        throw new NutixException('db connect error', [], $e);
                    }
                    break;
                }
            }
            self::$pdoStack[$connectAlias] = self::$pdo;
        }

        self::$dbName = $connectData['base'];
        self::$dbType = $connectData['db_type'];

        self::$debugMode = DB_DEBUG;

        self::$models = ModelStack::getInstance();

        self::$dbConnected = true;
    }


    public static function checkDbConnected() : bool 
    {
        return self::$dbConnected;
    }


    /**
     * Отладочный вывод всех запросов SQL каждого HTTP запроса, который будет выполнен
     * при включенном режиме debug, с параметрами и выводом исключения если была ошибка, в лог,
     * который будет сохранен как текстовый файл в папку 'files/core/db_debug', с именем файла
     * [http_request_uri]-[datetime].txt
     * todo -> добавить вывод данных исключения в файл лога
     */
    private static function saveDebugData() : void 
    {
        $datetime = DateHelper::nowFormated('d_m_y_H_i_s');
        $fileName = App::$uri . '-' . $datetime . '.txt';
        $file = FILES_DIR . DS . 'core' . DS . 'db_debug' . DS . $fileName;
        $output = '';

        foreach (self::$debugLog as $log) {
            $output .= 'sql: ' . $log['sql'] . EOL;
            if (empty($log['bindings'])) $output .= '------------' . EOL;

            if (!empty($log['bindings'])) {
                $bindings = implode(', ', array_map(function ($key, $value) {
                    $valueType = gettype($value);
                    return "$key - ($valueType) $value";
                }, array_keys($log['bindings']), $log['bindings']));
                $output .= 'bindings: ' . $bindings . EOL . '------------' . EOL;
            }
        }
        File::saveDataToFile($output, $file);
    }


    private static function addDebugLog(string $sql, array $bindings = []) : void 
    {
        $log = [];

        $log['sql'] = $sql;
        if (!empty($bindings)) $log['bindings'] = $bindings;

        self::$debugLog[] = $log;
    }


    /*------Методы работы с индексами таблиц------*/

    /** 
     * todo -> добавить реализации для pgsql и sqlite
     * @return array
     */
    public function getIndexesList() : array 
    {
        $table = $this->tableName;

        switch (self::$dbType) {
            case 'mysql': {
                $sql = "SHOW INDEXES FROM $table";
                break;
            }
            default: return [];
        }
        $indexesData = self::pdoQuery($sql);
        $indexes = [];

        foreach ($indexesData as $indexData) {
            $keyName = $indexData['Key_name'];
            if ($keyName === 'PRIMARY') continue;

            if (!in_array($keyName, array_keys($indexes))) {
                $indexes[$keyName] = [
                    'fields' => [],
                    'type' => ($indexData['Non_unique'] === '1') ? 'simple' : 'unique',
                ];
            }
            $indexes[$keyName]['fields'][] = $indexData['Column_name'];
        }
        return $indexes;
    }


    public function controlIndexes() : void 
    { 
        if (!self::tableExists($this->tableName)) return;

        $modelIndexes = $this->indexes ?? [];
        if (count($modelIndexes) === 0) return;

        $modelKeys = array_keys($modelIndexes);
        $modelCount = count($modelKeys);
        $tableIndexes = $this->getIndexesList();
        $tableKeys = array_keys($tableIndexes);
        $tableCount = count($tableKeys);
        $toDel = [];
        $toAdd = [];

        if ($modelCount < $tableCount) {
            $toDel = array_diff($tableKeys, $modelKeys);
        }
        if ($modelCount > $tableCount) {
            $toAdd = array_diff($modelKeys, $tableKeys);
        }
        if ($modelCount === $tableCount and count(array_diff($modelKeys, $tableKeys)) > 0) {
            $toDel = array_diff($tableKeys, $modelKeys);
            $toAdd = array_diff($modelKeys, $tableKeys);
        }

        $allIndexes = array_merge($modelIndexes, $tableIndexes);
        foreach ($toDel as $indexName) $this->dropIndex($indexName);
        foreach ($toAdd as $indexName) {
            $data = $allIndexes[$indexName];
            $this->createIndex($indexName, $data['fields'], ($data['type'] === 'unique'));
        }
    }


    public function createIndexes() : void 
    { 
        if (empty($this->indexes)) return;

        foreach ($this->indexes as $name => $data) {

            $this->createIndex($name, $data['fields'], ($data['type'] === 'unique'));
        }
    }


    /** 
     * @param string $name
     * @param string[] $fields
     * @param bool $unique
     */
    public function createIndex(string $name, array $fields, bool $unique = false) : void 
    {
        $fieldsStr = implode(', ', $fields);

        $sql = 'CREATE ';
        if ($unique) $sql .= 'UNIQUE ';
        $sql .= "INDEX $name ON {$this->tableName}($fieldsStr)";
        $this->execute($sql);
    }


    /** 
     * @throws NutixException
     */
    public function dropIndex(string $name) : void 
    {

        switch (self::$dbType) {
            case 'mysql': {
                if (!in_array($name, array_keys($this->getIndexesList()))) {
                    throw new NutixException('wrong index name', [
                        'index_name' => $name,
                    ]);
                }
                $sql = "DROP INDEX $name ON `{$this->tableName}`";
                break;
            }
            case 'pgsql':
            case 'sqlite': {
                $sql = "DROP INDEX IF EXISTS $name";
                break;
            }
            default: return;
        }
        $this->execute($sql);
    }


    public function dropAllIndexes() : void 
    { 

        foreach (array_keys($this->getIndexesList()) as $indexName) {
            $this->dropIndex($indexName);
        }
    }

    /*-----------------*/


    /*------Методы работы с блокировкой таблиц------*/

    public static function addTableAccess(string $table) : void 
    { 
        if ($table === 'tablesaccess' or self::$connectionName !== MAIN_DB_CONNECTION) return;

        $access = self::$models->tablesAccess;
        if ($access->rowExists(
            $access->val('id', '`table` LIKE ?', [$table]))
        ) return;

        $access->insert([
            'table' => $table,
            'access' => 1,
            'used_by' => '',
        ]);
    }


    public static function tableAccess(string $table) : bool 
    {
        $suffixes = implode('|', Core::ALLOWED_TEMP_TABLE_SUFFIXES);
        if (
            $table === 'tablesaccess' or
            preg_match("/($suffixes)$/", $table) or
            self::$connectionName !== MAIN_DB_CONNECTION
        ) {
            return true;
        }

        $accessData = self::$models->tablesAccess->row(
            'SELECT * FROM %table% WHERE `table` LIKE ?', [$table]
        );
        return ($accessData['access'] === 1 or $accessData['used_by'] === App::$user);
    }


    /** 
     * @throws NutixException
     */
    public static function checkTableAccess(string $table) : void 
    {
        if (!self::tableAccess($table)) {
            //var_dump('db table locked'); exit;
            throw new NutixException('db table locked', [
                'table' => $table,
            ]);
        }
    }


    /** 
     * @param string[] $tables
     * @param int $access
     * @param string $usedBy
     *
     * @throws NutixException
     */
    private static function setTablesAccess(array $tables, int $access, string $usedBy) : void 
    {
        if (!LOCK_DB_TABLES or self::$connectionName !== MAIN_DB_CONNECTION) return;
        if (count($tables) === 0) return;

        foreach ($tables as $table) {
            if (!self::tableExists($table)) {
                throw new NutixException('wrong table name', [
                    'table' => $table,
                ]);
            }

            $accessModel = self::$models->tablesAccess;
            $accessData = $accessModel->row(
                'SELECT * FROM %table% WHERE `table` LIKE ?', [$table]
            );
            $accessModel->update([
                'access' => $access,
                'used_by' => $usedBy,
            ], $accessData['id']);
        }
    }


    /** 
     * @param string[] $tables
     */
    public static function lockTables(array $tables) : void 
    {
        if (self::$connectionName !== MAIN_DB_CONNECTION) return;
        self::setTablesAccess($tables, 0, App::$user);
    }


    /** 
     * @param string[] $tables
     */
    public static function unlockTables(array $tables) : void 
    {
        if (self::$connectionName !== MAIN_DB_CONNECTION) return;
        self::setTablesAccess($tables, 1, '');
    }


    public static function lockAllTables() : void 
    { 
        if (!LOCK_DB_TABLES or self::$connectionName !== MAIN_DB_CONNECTION) return;

        self::$models->tablesAccess->execute(
            "UPDATE %table% SET `access` = 0, `used_by` = ?",
            [App::$user]
        );
    }


    public static function unlockAllTables() : void 
    { 
        if (!LOCK_DB_TABLES or self::$connectionName !== MAIN_DB_CONNECTION) return;

        self::$models->tablesAccess->execute(
            "UPDATE %table% SET `access` = 1, `used_by` = ''"
        );
    }


    public static function unlockUserTables() : void 
    {
        if (self::$connectionName !== MAIN_DB_CONNECTION) return;
        $userAlias = App::$userAlias;
        $user = App::$user;

        $sqlUser = (in_array($userAlias, [
            Users::APP_USER_GUEST, 
            Users::APP_USER_CRON
        ])) ? "'$user'" : "'$userAlias%'";

        $tables = self::$models->tablesAccess->col(
            "SELECT `table` FROM %table% WHERE `used_by` LIKE $sqlUser"
        );
        self::unlockTables($tables);
    }


    public static function unlockOldUsages() : void 
    {
        if (self::$connectionName !== MAIN_DB_CONNECTION) return;
        $threshold = 300000;
        $tables = self::$models->tablesAccess->rows(
            'SELECT * FROM %table% WHERE `access` = 0'
        );

        $toUnlock = [];
        foreach ($tables as $table) {
            $loginTimeMs = explode('@', $table['used_by'])[1];
            if ((DateHelper::nowMS() - (int) $loginTimeMs) > $threshold) {
                $toUnlock[] = $table['table'];
            }
        }
        if (count($toUnlock) === 0) return;

        $slots = self::getSlots($toUnlock);
        self::$models->tablesAccess->execute(
            "UPDATE %table% SET `access` = 1, `used_by` = '' WHERE `table` IN($slots)", $toUnlock
        );
    }


    public static function fixTablesAccess() : void 
    {
        if (self::$connectionName !== MAIN_DB_CONNECTION) return;
        $tables = self::$models->tablesAccess->col(
            "SELECT `table` FROM %table% WHERE 
            (`access` = 0 AND `used_by` = '') OR
            (`access` = 1 AND `used_by` != '')"
        );
        if (count($tables) === 0) return;

        $slots = self::getSlots($tables);
        self::$models->tablesAccess->execute(
            "UPDATE %table% SET `access` = 1, `used_by` = '' WHERE `table` IN($slots)", $tables
        );
    }

    /*-----------------*/


    /** 
     * Сохранить лог о изменении данных в базе
     * @param int $id
     * @param string $type
     * @param array $data
     */
    private function addUpdatesLog(int $id, string $type, array $data) : void 
    {

        if (
            $this->saveUpdates and
            SAVE_DB_UPDATES_HISTORY and
            self::$connectionName === MAIN_DB_CONNECTION
        ) {

            App::$dbUpdatesLog->insert([
                'table_name' => $this->tableName,
                'query' => App::$uri,
                'row_id' => $id,
                'type' => $type,
                'data' => json_encode($data),
            ]);
        }
    }


    /** 
     * Установить карту полей таблицы
     * @param string[] $map
     */
    public function setFieldsMap(array $map) : void 
    {

        $this->fieldsMap = $map;
        if (empty($this->defaultFieldsMap)) $this->defaultFieldsMap = $map;
    }


    public function resetFieldsMap() : void
    {
        if (!empty($this->defaultFieldsMap)) $this->fieldsMap = $this->defaultFieldsMap;
    }


    /** 
     * Установить список индексов таблицы
     * @param string[] $map
     */
    public function setIndexesList(array $indexes) : void 
    {

        $this->indexes = $indexes;
    }


    /** 
     * Добавить к данным дефолтные системные данные
     * @param array $data
     * @param array $fields
     *
     * @throws NutixException
     * @return array
     */
    private function addSystemData(array $data, array $fields) : array 
    {
        if (
            self::$connectionName !== MAIN_DB_CONNECTION or
            !self::$addSystemData
        ) {
            return $data;
        }
        foreach ($fields as $field) {

            if (!in_array($field, array_keys($this->fieldsMap))) continue;
            $value = null;

            switch ($field) {

                case 'user_id': {

                    $userId = App::$session->userId;
                    if (empty($userId)) {

                        if (empty(SYSTEM_USER)) {
                            throw new NutixException(
                                'missing param SYSTEM_USER at config.php'
                            );
                        }
                        $userId = SYSTEM_USER;
                    }

                    $value = $userId;
                    break;
                }
                case 'create_time':
                case 'update_time': {

                    $value = DateHelper::now();
                    break;
                }
            }
            $data[$field] = $value;
        }

        return $data;
    }


    /** 
     * Добавить доп. поля для запросов, использующих алиасы (AS) или поля из других таблиц (запросы JOIN),
     * для которых нету значений в массиве $this->fieldsMap
     *
     * @param string[] $fieldsMap
     */
    public function addCustomFields(array $fieldsMap) : void 
    {

        $this->fieldsMap = array_merge($this->fieldsMap, $fieldsMap);
    }


    /** 
     * Очистить входящие данные от значений, ключей которых нету в конфигурации
     * полей модели
     * @param array $data
     *
     * @return array
     */
    private function filterData(array $data) : array 
    {
        $_data = [];

        foreach ($data as $key => $value) {
            if ($key === 'id') continue;
            if (in_array($key, array_keys($this->fieldsMap))) {
                $_data[$key] = $value;
            }
        }
        return $_data;
    }


    /** 
     * Привести данные к нужным типам, работает рекурсивно
     * @param array $data
     *
     * @return array
     */
    public function setDataTypes(array $data) : array 
    {
        $_data = [];

        foreach ($data as $key => $value) {

            if (is_array($value)) $_data[$key] = $this->setDataTypes($value);

            else if (in_array($key, array_keys($this->fieldsMap))) {
                $_data[$key] = $value;

                if (isset($this->fieldsMap[$key])) {
                    if ($this->fieldsMap[$key] === 'text') {

                        settype($_data[$key], 'string');

                    } else if ($this->fieldsMap[$key] === 'bool_int') {

                        settype($_data[$key], 'int');

                    } else {
                        settype($_data[$key], $this->fieldsMap[$key]);
                    }
                }
            }
        }
        return $_data;
    }


    /** 
     * Выполнить действие с данными
     *
     * @param mixed $data
     * @param string $action
     * @param string $checkFor На какой тип проверять данные если это не массив
     *
     * @return mixed
     */
    public static function handleData($data, string $action = '', string $checkFor = 'string') 
    {
        $checkMethod = 'is_' . $checkFor;

        if (self::$handleDataActionsSettings[$action]) {
            switch ($action) {

                case 'trim': {
                    $method = function ($data) {
                        return trim($data);
                    };
                    break;
                }
                case 'html_entity_decode': {
                    $method = function ($data) {
                        return html_entity_decode(
                            html_entity_decode($data, ENT_COMPAT | ENT_HTML401, 'UTF-8')
                        );
                    };
                    break;
                }
                default: return $data;
            }

            if (is_array($data)) {
                $handled = [];
                foreach ($data as $key => $value) {
                    if (is_array($value)) $handled[$key] = self::handleData($value, $action, $checkFor);
                    else if ($checkMethod($value)) $handled[$key] = $method($value);
                    else $handled[$key] = $value;
                }
                return $handled;
            }

            if ($checkMethod($data)) return $method($data);
            else return $data;

        } else return $data;
    }


    /**
     * Заменить в SQL запросе специальное слово %table% на имя таблицы
     */
    private function addTableName(string $sql, string $table = '') : string
    {
        if (empty($table)) $table = $this->tableName;
        return str_replace('%table%', $table, $sql);
    }


    /** 
     * Получить текущий список таблиц базы
     */
    public static function getTablesList() : array 
    {
        $list = [];

        switch (self::$dbType) {
            case 'mysql': {
                $tables = self::pdoQuery("SHOW TABLES");
                foreach ($tables as $data) {
                    $list[] = $data['Tables_in_'.self::$dbName] ?? '';
                }
                break;
            }
        }
        return $list;
    }


    public static function tableExists(string $tableName) : bool 
    {

        return (in_array($tableName, self::getTablesList()));
    }


    /** 
     * Создание таблицы и ее структуры
     * @param string $table
     * @param string[] $fields
     */
    public function initTableStructure(string $table = '', array $fields = []) : void
    {
        if (empty($table)) $table = $this->tableName;
        if (empty($fields)) $fields = $this->fieldsMap;
        $tableData = [];

        foreach ($fields as $field => $type) {
            $fieldData = '';

            switch ($type) {
                case 'int': {
                    $fieldData .= "`$field` INT(11) UNSIGNED";
                    if ($field === 'id') $fieldData .= ' AUTO_INCREMENT PRIMARY KEY';
                    break;
                }
                case 'bool_int': {
                    $fieldData .= "`$field` INT(1) UNSIGNED";
                    break;
                }
                case 'float': {
                    $fieldData .= "`$field` FLOAT";
                    break;
                }
                case 'string': {
                    $fieldData .= "`$field` VARCHAR(191)";
                    break;
                }
                case 'text': {
                    $fieldData .= "`$field` TEXT";
                    break;
                }
            }
            $tableData[] = $fieldData;
        }
        $tableStr = implode(', ', $tableData);

        switch (self::$dbType) {
            case 'mysql': {
                $sql = "CREATE TABLE IF NOT EXISTS $table ($tableStr) ENGINE=INNODB";
                break;
            }
        }
        self::pdoExecute($sql);
    }


    /*------Временная копия таблицы и временные таблицы------*/

    /**
     * Временная копия таблицы используется для тестирования новых данных
     * и временного хранения данных отдельно от основной таблицы.
     * Можно создать любое к-во временных таблиц, передавая суффикс для их имени.
     * Также можно создать отдельную таблицу передав набор полей.
     */
    public function initTempTable(string $suffix = self::TEMP_TABLE_SUFFIX, array $fields = []) : void
    {
        $table = $this->getTempName($suffix);
        if (self::tableExists($table)) return;

        $this->initTableStructure($table, $fields);
    }


    /** 
     * @throws NutixException
     */
    public function getTempName(string $suffix) : string
    {
        if (
            self::$connectionName === MAIN_DB_CONNECTION and
            !in_array($suffix, Core::ALLOWED_TEMP_TABLE_SUFFIXES)
        ) {
            throw new NutixException(
                'wrong temp table suffix',
                ['suffix' => $suffix]
            );
        }
        return $this->tableName . $suffix;
    }


    /**
     * @throws NutixException
     */
    private function checkTempTable(string $table) : void
    {
        if (!self::tableExists($table)) {
            throw new NutixException('temp table not exists', [
                'table' => $table,
            ]);
        }
    }


    /** 
     * @param array $data
     * @param bool $throwException
     * @param string $suffix
     */
    public function tempInsert(
        array $data,
        bool $throwException = true,
        string $suffix = self::TEMP_TABLE_SUFFIX
    ) : int
    {

        $table = $this->getTempName($suffix);
        $this->checkTempTable($table);

        return $this->insert($data, $throwException, $table);
    }


    /** 
     * @param array $data
     * @param int $id
     * @param bool $throwException
     * @param string $suffix
     */
    public function tempUpdate(
        array $data,
        int $id,
        bool $throwException = true,
        string $suffix = self::TEMP_TABLE_SUFFIX
    ) : bool
    {

        $table = $this->getTempName($suffix);
        $this->checkTempTable($table);

        return $this->update($data, $id, $throwException, $table);
    }


    /** 
     * @param string $predicate
     * @param array $bindings
     * @param bool $onlyId
     * @param string $suffix
     *
     * @return mixed
     */
    public function tempVal(
        string $field,
        string $predicate,
        array $bindings = [],
        string $suffix = self::TEMP_TABLE_SUFFIX
    )
    {

        $table = $this->getTempName($suffix);
        $this->checkTempTable($table);

        return $this->val($field, $predicate, $bindings, $table);
    }


    /** 
     * @return array
     */
    public function tempAll(string $suffix = self::TEMP_TABLE_SUFFIX) : array 
    {
        $table = $this->getTempName($suffix);
        $this->checkTempTable($table);

        return $this->rows("SELECT * FROM $table");
    }


    /**
     * @param string $sql
     * @param array $bindings
     * @param string $suffix
     *
     * @return array
     */
    public function tempRows(
        string $sql,
        array $bindings = [],
        string $suffix = self::TEMP_TABLE_SUFFIX
    ) : array
    {
        $table = $this->getTempName($suffix);
        $this->checkTempTable($table);

        return $this->rows($sql, $bindings, $table);
    }


    public function truncateTempTable(string $suffix = self::TEMP_TABLE_SUFFIX) : bool 
    {
        $table = $this->getTempName($suffix);
        $this->checkTempTable($table);

        return $this->truncateTable($table);
    }


    public function deleteTempTable(string $suffix = self::TEMP_TABLE_SUFFIX) : void 
    {
        $table = $this->getTempName($suffix);
        $this->checkTempTable($table);

        $this->deleteTable($table);
    }


    public function initTempTableIfNotExists(
        string $suffix = self::TEMP_TABLE_SUFFIX,
        array $fields = []
    ) : void
    {
        $table = $this->getTempName($suffix);

        if (!self::tableExists($table)) {
            $this->initTempTable($suffix, $fields);
            $this->truncateTempTable($suffix);
        }
    }


    public function deleteTempTableIfExists(string $suffix = self::TEMP_TABLE_SUFFIX) : void
    {
        $table = $this->getTempName($suffix);
        
        if (self::tableExists($table)) {
            $this->deleteTempTable($suffix);
        }
    }

    /*-----------------*/


    /*------Транзакции------*/

    /** 
     * Методы управления транзакциями.
     *
     * Принцип работы:
     * 1. При старте транзакции идет проверка не запущена ли уже транзакция на более высоком уровне.
     *    Если нет, то запускается транзакция и в сессию записывается токен транзакции, состоящий из md5 хэша
     *    константы __METHOD__, если ее значение передано параметром $method
     * 2. При откате транзакции ее токен очищается из сессии
     * 3. При попытке коммита транзакции идет проверка соответствия токена модели токену транзакции,
     *    чтобы закоммитить ее могли только модель и метод что ее запустили
     */

    /**
     * Старт транзакции
     */
    public static function startTransaction(string $code = '') : void 
    {

        if (App::$session->transaction === true) return;

        App::$session->transaction = true;
        App::$session->transactionToken = md5($code);

        self::$pdo->beginTransaction();
    }


    /** 
     * Откат транзакции
     */
    public static function rollbackTransaction(bool $unlock = true) : void 
    {

        if (!App::$session->transaction) return;

        App::$session->transaction = false;
        App::$session->transactionToken = '';
        self::$pdo->rollBack();

        if ($unlock) self::unlockUserTables();
    }


    /** 
     * Коммит транзакции с применением изменений в базе
     */
    public static function commitTransaction(string $code = '', bool $unlock = true) : void 
    {

        if (!App::$session->transaction) return;
        if (md5($code) !== App::$session->transactionToken) return;

        App::$session->transaction = false;
        App::$session->transactionToken = '';
        self::$pdo->commit();

        if ($unlock) self::unlockUserTables();
    }

    /*-----------------*/


    /** 
     * Базовый метод для выборки данных без подготовки запроса
     *
     * @throws NutixException
     * @return array
     */
    private static function pdoQuery(string $sql) : array 
    {
        $rows = [];

        try {

            if (self::$debugMode) self::addDebugLog($sql);
            $stmt = self::$pdo->query($sql);
            while ($row = $stmt->fetch())
            {
                $rows[] = $row;
            }

        } catch (\Exception $e) {

            if (self::$debugMode) self::saveDebugData();
            throw new NutixException('PDO query error', [], $e);
        }
        if (self::$debugMode) self::saveDebugData();
        return $rows;
    }


    /** 
     * Базовый метод для выборки данных с подготовкой запроса
     *
     * @param string $sql
     * @param array $bindings
     *
     * @throws NutixException
     * @return array
     */
    private static function pdoPreparedQuery(string $sql, array $bindings) : array 
    {
        $rows = [];

        try {

            if (self::$debugMode) self::addDebugLog($sql, $bindings);
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($bindings);
            while ($row = $stmt->fetch())
            {
                $rows[] = $row;
            }

        } catch (\Exception $e) {

            if (self::$debugMode) self::saveDebugData();
            throw new NutixException('PDO prepared query error', [], $e);
        }
        if (self::$debugMode) self::saveDebugData();
        return $rows;
    }


    /** 
     * Базовый метод для работы с данными без подготовки запроса
     *
     * @throws NutixException
     */
    private static function pdoExecute(string $sql) : bool 
    {
        try {

            if (self::$debugMode) self::addDebugLog($sql);
            self::$pdo->exec($sql);

        } catch (\Exception $e) {

            if (self::$debugMode) self::saveDebugData();
            throw new NutixException('PDO execute error', [], $e);
        }
        if (self::$debugMode) self::saveDebugData();
        return true;
    }


    /** 
     * Базовый метод для работы с данными с подготовкой запроса
     *
     * @param string $sql
     * @param array $bindings
     * @throws NutixException
     */
    private static function pdoPreparedExecute(string $sql, array $bindings) : bool 
    {
        try {

            if (self::$debugMode) self::addDebugLog($sql, $bindings);
            $stmt = self::$pdo->prepare($sql);
            $result = $stmt->execute($bindings);

        } catch (\Exception $e) {

            if (self::$debugMode) self::saveDebugData();
            throw new NutixException('PDO prepared execute error', [], $e);
        }
        if (self::$debugMode) self::saveDebugData();
        return $result;
    }


    /** 
     * Выполнение SQL запроса работы с данными
     *
     * @param string $sql
     * @param array $bindings
     * @param string $table
     *
     * @throws NutixException
     * @return bool
     */
    public function execute(string $sql, array $bindings = [], string $table = '') : bool
    {
        try {
            if (empty($table)) $table = $this->tableName;
            self::checkTableAccess($table);
            $sql = $this->addTableName($sql, $table);

            $result = (empty($bindings)) ?
                self::pdoExecute($sql) :
                self::pdoPreparedExecute($sql, self::handleData($bindings, 'trim'));

        } catch (\Exception $e) {
            throw new NutixException('DB execute error', [], $e);
        }
        return $result;
    }


    /** 
     * @param array $bindings
     * @return string[]
     */
    public function explainQuery(string $sql, array $bindings = []) : array 
    {

        $sql = 'EXPLAIN ' . $sql;
        return self::pdoPreparedQuery(
            $this->addTableName($sql),
            self::handleData($bindings, 'trim')
        );
    }


    /** 
     * Есть ли в таблице запись с таким ID
     */
    public function rowExists(int $id) : bool 
    {

        $row = $this->row("SELECT * FROM %table% WHERE `id` = ?", [$id]);
        return count($row) > 0;
    }


    /** 
     * Получить все строки выборки по SQL запросу
     * -> Возвращает двухмерный массив вида [
     * 0 => [поле => значение, поле => значение ...],
     * 1 => [поле => значение, поле => значение ...],
     * ... ]
     * -> Чтобы получить лишь некоторые поля из каждой строки выборки, нужно добавить
     * их в SQL запрос вместо "*"
     * -> При выборке несуществующего поля/полей возвращает пустой массив
     * @param string $sql
     * @param array $bindings
     * @param string $table
     *
     * @return array
     */
    public function rows(string $sql, array $bindings = [], string $table = '') : array
    {
        if (empty($table)) $table = $this->tableName;
        $sql = $this->addTableName($sql, $table);

        $rows = (empty($bindings)) ?
            self::pdoQuery($sql) :
            self::pdoPreparedQuery($sql, self::handleData($bindings, 'trim'));

        return self::handleData(
            self::setDataTypes($rows), 'html_entity_decode'
        );
    }


    /** 
     * Получить одну строку выборки по SQL запросу
     * -> Возвращает одномерный массив вида [поле => значение, поле => значение ...]
     * -> Для получения только некоторых полей из строки выборки, нужно указать их в SQL запросе вместо "*"
     * -> При выборке несуществующего поля/полей возвращает пустой массив
     * @param string $sql
     * @param array $bindings
     * @param string $table
     *
     * @return array
     */
    public function row(string $sql, array $bindings = [], string $table = '') : array
    {
        if (empty($table)) $table = $this->tableName;

        $rows = $this->rows($sql, $bindings, $table);
        return (count($rows) > 0) ? $rows[0] : [];
    }


    /** 
     * Получить одно поле из строки выборки по SQL запросу
     * -> Возвращает приведенное к типу простое значение
     * -> При выборе несуществующего поля возвращает null
     * @param string $sql
     * @param string $field
     * @param array $bindings
     * @param string $table
     *
     * @return mixed
     */
    public function val(string $field, string $predicate, array $bindings = [], string $table = '') 
    {
        if (empty($table)) $table = $this->tableName;

        $sql = "SELECT $field FROM $table WHERE $predicate";
        $row = $this->row($sql, $bindings);

        $value = (isset($row[$field])) ? $row[$field] : null;
        if (in_array($field, array_keys($this->fieldsMap))) {
            if ($this->fieldsMap[$field] === 'text') settype($value, 'string');
            else settype($value, $this->fieldsMap[$field]);
        }
        return $value;
    }


    /** 
     * Получить одну колонку выборки по SQL запросу
     * -> Возвращает одномерный индексированный массив
     * -> При выборке несуществующего поля/полей возвращает пустой массив
     * @param string $sql
     * @param array $bindings
     * @param string $type К этому типу будут приведены все значения выбранной колонки
     *
     * @return array
     */
    public function col(string $sql, array $bindings = [], string $type = 'string') : array 
    {
        $sql = $this->addTableName($sql);

        $rows = $this->rows($sql, self::handleData($bindings, 'trim'));
        $col = array_map(function ($row) use ($type) {
            $keys = array_keys($row);
            $value = $row[$keys[0]] ?? '';
            settype($value, $type);
            return $value;
        }, $rows);

        return self::handleData($col, 'html_entity_decode');
    }


    /** 
     * Получить подстроку для SQL запроса для подстановки значений
     * [1, 2, 3] -> '?, ?, ?'
     * @param array $elements
     */
    public static function getSlots(array $elements) : string 
    {

        return implode(', ', array_map(function () {
            return '?';
        }, $elements));
    }


    /**
     * Получить подготовленную строку для IN()
     * @param string[] $values
     */
    public static function getStringForInStatement(array $values) : string
    {
        array_walk($values, function (&$val) {
            if (is_array($val)) {
                $val = implode(' ', $val);
            }
            $val = addslashes($val);
            $val = "'$val'";
        }); unset($val);
        $result = implode(',', $values);
        return $result;
    }


    /** 
     * Получить все записи таблицы
     * @return array
     */
    public function all() : array 
    {

        return $this->rows('SELECT * FROM %table%');
    }


    /** 
     * Получить только активные записи таблицы
     * @return array
     */
    public function allActive() : array 
    {

        return $this->rows('SELECT * FROM %table% WHERE `active` = 1');
    }


    /** 
     * Добавить запись в таблицу
     * @param array $data
     * @param bool $throwException
     * @param string $table Можно передать например имя тестовой версии данной таблицы
     *
     * @throws NutixException
     * @return int ID новой записи
     */
    public function insert(array $data, bool $throwException = true, string $table = '') : int 
    {

        $data = $this->filterData($data);
        $data = $this->setDataTypes(self::handleData($data, 'trim'));
        $data = $this->addSystemData($data, ['user_id', 'create_time']);

        try {
            $table = ($table === '') ? $this->tableName : $table;
            if (App::checkAppInited()) {
                self::checkTableAccess($table);
            }

            $columns = array_keys($data);
            $columns = array_map(function($column) {
                return "`$column`";
            }, $columns);
            $columns = implode(', ', $columns);

            $values = array_values($data);
            $slots = self::getSlots($values);

            $sql = "INSERT INTO $table ($columns) VALUES ($slots)";
            self::pdoPreparedExecute($sql, $values);

            $id = $this->getHighestId($table);

        } catch (\Exception $e) {

            if (!$throwException) return 0;
            throw new NutixException('DB insert error', [], $e);
        }

        $this->addUpdatesLog($id, 'insert', $data);
        return $id;
    }


    /** 
     * Обновить запись в таблице
     * @param array $data
     * @param int $id
     * @param bool $throwException
     * @param string $table Можно передать например имя тестовой версии данной таблицы
     *
     * @throws NutixException
     * @return bool
     */
    public function update(array $data, int $id, bool $throwException = true, string $table = '') : bool 
    {

        $data = $this->filterData($data);
        $data = $this->setDataTypes(self::handleData($data, 'trim'));
        $sysFields = (App::$session->userId === SYSTEM_USER) ? ['update_time'] : ['user_id', 'update_time'];
        $data = $this->addSystemData($data, $sysFields);

        try {
            $table = ($table === '') ? $this->tableName : $table;
            if (App::checkAppInited()) {
                self::checkTableAccess($table);
            }

            $columns = implode(', ', array_map(function($key) {
                return "`$key` = ?";
            }, array_keys($data)));

            $sql = "UPDATE $table SET $columns WHERE `id` = $id";
            self::pdoPreparedExecute($sql, array_values($data));

        } catch (\Exception $e) {

            if (!$throwException) return false;
            throw new NutixException('DB update error', [], $e);
        }

        $this->addUpdatesLog($id, 'update', $data);
        return true;
    }


    /** 
     * Создать уникальный ID для указаного поля в таблице
     */
    public function getUniqueId(string $field, int $charsNum = 3) : string 
    {

        $code = StringHelper::generateRandom($charsNum, true);
        $id = $this->val('id', "`$field` LIKE ?", [$code]);

        if ($this->rowExists($id)) {
            $this->getUniqueId($field, $charsNum);
        }
        return $code;
    }


    /** 
     * Получить к-во записей в таблице
     */
    public function getCount() : int 
    {

        $this->addCustomFields(['count' => 'int']);
        $row = $this->row("SELECT COUNT(*) AS `count` FROM %table%");
        return $row['count'];
    }


    /**
     * Получить к-во записей выборки
     *
     * @param string $predicate
     * @param array $bindings
     * @param string $table
     */
    public function getCountFiltered(string $predicate, array $bindings = [], string $table = '') : int
    {
        if (empty($table)) $table = $this->tableName;

        $this->addCustomFields(['count' => 'int']);
        $sql = "SELECT COUNT(*) AS `count` FROM %table% WHERE $predicate";
        $row = $this->row($sql, $bindings, $table);
        return $row['count'];
    }


    /** 
     * Получить к-во строк в последней выборке
     */
    public function getLastResultsNum() : int 
    {

        $this->addCustomFields(['rows_num' => 'int']);
        $row = $this->row("SELECT FOUND_ROWS() AS `rows_num`");
        return $row['rows_num'];
    }


    /** 
     * Дублировать строку, вернуть ID новой строки или 0 при неудаче
     */
    public function duplicateRow(int $id) : int 
    {

        $data = $this->row('SELECT * FROM %table% WHERE `id` = ?', [$id]);
        array_shift($data);

        return $this->insert($data);
    }


    /** 
     * Удалить запись с данным ID
     * @throws NutixException
     */
    public function deleteRow(int $id, string $table = '') : void 
    {

        try {
            $table = ($table === '') ? $this->tableName : $table;
            if (App::checkAppInited()) {
                self::checkTableAccess($table);
            }
            self::pdoPreparedExecute("DELETE FROM $table WHERE `id` = ?", [$id]);

        } catch (\Exception $e) {
            throw new NutixException('DB delete row error', [], $e);
        }

        $this->addUpdatesLog($id, 'delete', []);
    }


    /** 
     * Очистить таблицу от данных
     * @throws NutixException
     */
    public function truncateTable(string $table = '') : bool 
    {
        $table = ($table === '') ? $this->tableName : $table;

        try {
            $result = self::pdoExecute("TRUNCATE TABLE $table");

        } catch (\Exception $e) {
            throw new NutixException('DB truncate table error', [], $e);
        }

        return $result;
    }


    public function deleteTable(string $table = '') : void 
    { 
        $table = ($table === '') ? $this->tableName : $table;

        self::pdoExecute("DROP TABLE $table");
    }


    public function getHighestId(string $table = '') : int
    {
        $table = ($table === '') ? $this->tableName : $table;
        return $this->val('id', '1 ORDER BY `id` DESC LIMIT 1', [], $table);
    }


    /** 
     * Получить набор данных из таблицы в виде массива, где ключами будут
     * значения поля таблицы из параметра $key
     *
     * @param string $key
     * @param string $sql
     * @param array $bindings
     *
     * @return array
     */
    public function getMap(string $key, string $sql, array $bindings = []) : array 
    {

        $data = $this->rows($sql, $bindings);
        $map = [];
        if (count($data) === 0) return $map;

        foreach ($data as $item) {
            if (!in_array($key, array_keys($item))) continue;

            $map[$item[$key]] = $item;
        }
        return $map;
    }

}