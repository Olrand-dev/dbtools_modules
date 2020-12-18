<?php

declare(strict_types=1);

namespace NutixApp\SmsService\Src\Api;


use NutixApp\Core\Src\Http\ApiService;
use NutixApp\Core\Src\Http\HttpHandlerInterface;
use NutixApp\Core\Src\Db\NPDO;
use NutixApp\Core\Src\App;
use NutixApp\Core\Src\Utils\EditableListController;

class SaveSmsTemplateApiService extends ApiService implements HttpHandlerInterface 
{

    public $alias = 'save-sms-template';

    public $module = 'sms-service';

    public $route = 'api/sms-service/save-sms-template';

    public $needAuth = true;

    public $permissionsMap = [
        'admin' => 1,
        'investor' => 0,
        'manager' => 1,
        'courier' => 0,
        'warehouseman' => 0,
    ];


    public function run() : void 
    {
        $rowId = (int) App::$requestData['row_id'];
        $rowData = App::$requestData['row_data'];
        $model = NPDO::$models->smsTemplates;

        EditableListController::saveEditedRow($rowId, $rowData, $model);
        self::sendResponse();
    }

}