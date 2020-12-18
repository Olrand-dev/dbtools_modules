<?php

declare(strict_types=1);

namespace NutixApp\SmsService\Src\Models;


use NutixApp\Core\Src\Db\NPDO;

class SmsTemplatesModel extends NPDO 
{

    public $tableName = 'smstemplates';

    public $saveUpdates = true;

    private $fieldsMap = [
        'id' => 'int',
        'name' => 'string',
        'alias' => 'string',
        'template' => 'string',

        'user_id' => 'int',
        'create_time' => 'int',
        'update_time' => 'int',
    ];

    private $indexes = [];


    public function __construct() 
    {

        $this->setFieldsMap($this->fieldsMap);
        $this->setIndexesList($this->indexes);
        parent::__construct();
    }
    
}