<?php

declare(strict_types=1);

namespace NutixApp\GlobalQueue\Src\Pages;


use NutixApp\Core\Src\Http\Page;
use NutixApp\Core\Src\Http\HttpHandlerInterface;

class GlobalQueuePage extends Page implements HttpHandlerInterface 
{

    public $alias = 'global-queue';

    public $name = 'Очередь';

    public $template = 'global-queue';

    public $module = 'global-queue';

    public $route = 'global-queue/list';

    public $needAuth = true;

    public $libKits = [
        'system-js-kit',
        'system-css-kit',
        'core-css-kit',
    ];

    public $uiComponents = [
        'side-menu',
        'main-footer',
        'user-menu',
        'pagination',
    ];

    public $depAssets = [
        'js' => [
            'core' => ['layout-parts-vue.js', 'core.js'],
        ],
    ];

    public $menusData = [
        'main-side-left-menu' => [
            'icon' => 'fas fa-layer-group',
            'name' => 'Системная очередь',
            'position' => 10,
            'ui_id' => '6es',
            'active' => true,
        ],
    ];

    public $permissionsMap = [
        'admin' => 1,
        'investor' => 0,
        'manager' => 0,
        'courier' => 0,
        'warehouseman' => 0,
    ];


    public function run() : void 
    {
        self::$data = [];
    }

}