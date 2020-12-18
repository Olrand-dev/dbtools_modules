<?php

declare(strict_types=1);

namespace NutixApp\SmsService\Src\Models;


use NutixApp\Core\Src\Db\NPDO;

class SmsModel extends NPDO 
{

    public $tableName = 'sms';

    public $saveUpdates = true;

    private $fieldsMap = [
        'id' => 'int',
        'smsid' => 'string',
        'otherparts' => 'string',
        'smsalias' => 'string',
        'source' => 'string',
        'sourceid' => 'int',
        'phone' => 'string',
        'message' => 'string',
        'status' => 'string',

        'user_id' => 'int',
        'create_time' => 'int',
        'update_time' => 'int',
    ];

    private $indexes = [
        'STATUS' => [
            'fields' => ['status'], 
            'type' => 'simple',
        ],
        'STATUS_SOURCE_SOURCE_ID' => [
            'fields' => ['status', 'source', 'sourceid'], 
            'type' => 'simple',
        ],
    ];


    public function __construct() 
    {

        $this->setFieldsMap($this->fieldsMap);
        $this->setIndexesList($this->indexes);
        parent::__construct();
    }
    
}