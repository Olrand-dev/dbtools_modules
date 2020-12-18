<?php

declare(strict_types=1);

namespace NutixApp\GlobalQueue\Src\Models;


use NutixApp\Core\Src\Db\NPDO;

class GlobalTasksModel extends NPDO 
{

    public $tableName = 'globaltasks';

    public $saveUpdates = true;

    private $fieldsMap = [
        'id' => 'int',
        'name' => 'string',
        'desc' => 'text',
        'type' => 'string',
        'code' => 'string',
        'status' => 'string',
        'priority' => 'int',
        'cursor' => 'int',
        'perc_compl' => 'int',
        'finished' => 'int',
        'finish_time' => 'int',
        'files_removed' => 'bool_int',
        'comment' => 'text',

        'user_id' => 'int',
        'create_time' => 'int',
        'update_time' => 'int',
    ];

    private $indexes = [
        
    ];


    public function __construct() 
    {
        $this->setFieldsMap($this->fieldsMap);
        $this->setIndexesList($this->indexes);
        parent::__construct();
    }
    
}