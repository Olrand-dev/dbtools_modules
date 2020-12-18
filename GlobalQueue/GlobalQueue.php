<?php

declare(strict_types=1);

namespace NutixApp\GlobalQueue;

use NutixApp\GlobalQueue\Src\Tasks\CreateExportFileTask;
use NutixApp\GlobalQueue\Src\Tasks\CreateNomenclatureControlFileTask;
use NutixApp\GlobalQueue\Src\Tasks\ImportContractorProdsTask;
use NutixApp\GlobalQueue\Src\Tasks\ImportImagesTask;
use NutixApp\GlobalQueue\Src\Tasks\ImportNomenclatureControlFileTask;
use NutixApp\GlobalQueue\Src\Tasks\ProdsCacheUpdateDatacacheSelectedTask;
use NutixApp\GlobalQueue\Src\Tasks\ProdsCacheUpdateDynamicTask;
use NutixApp\GlobalQueue\Src\Tasks\ProdsCacheUpdateFullTask;
use NutixApp\GlobalQueue\Src\Tasks\UpdateOrdersStatTask;
use NutixApp\GlobalQueue\Src\Tasks\NomenclatureImportConfirmTask;
use NutixApp\GlobalQueue\Src\Tasks\UpdateStoresCacheFilesTask;

abstract class GlobalQueue 
{

    public static $name = 'Global Queue';

    public static $alias = 'global-queue';

    public static $assets = [
        'views_dir' => 'public/views',
        'js_dir' => 'public/js',
        'css_dir' => 'public/css',
    ];

    public static $folders = [];

    public static $dependencies = [];

    public static $sessionDataMap = [];

    public const TASK_STATUS_NEW = 'new';
    public const TASK_STATUS_AT_WORK = 'at_work';
    public const TASK_STATUS_FINISHED = 'finished';
    public const TASK_STATUS_FAILED = 'failed';

    public const TASK_STATUS_DEFAULT = self::TASK_STATUS_NEW;

    public const TASK_STATUS_NAMES = [
        self::TASK_STATUS_NEW => 'Новые',
        self::TASK_STATUS_AT_WORK => 'В работе',
        self::TASK_STATUS_FINISHED => 'Завершенные',
        self::TASK_STATUS_FAILED => 'Проблемные',
    ];


    public const TASK_TYPE_UPDATE_PRODS_CACHE_FULL = 'update_prods_cache_full';
    public const TASK_TYPE_UPDATE_PRODS_CACHE_DYNAMIC = 'update_prods_cache_dynamic';
    public const TASK_TYPE_UPDATE_DATACACHE_SELECTED = 'update_datacache_selected';
    public const TASK_TYPE_CREATE_EXPORT_FILE = 'create_export_file';
    public const TASK_TYPE_IMPORT_PROD_IMAGES = 'import_prod_images';
    public const TASK_TYPE_CREATE_NOMENCLATURE_CONTROL_FILE = 'create_nom_control_file';
    public const TASK_TYPE_IMPORT_NOMENCLATURE_CONTROL_FILE = 'import_nom_control_file';
    public const TASK_TYPE_NOMENCLATURE_IMPORT_CONFIRM = 'nom_import_confirm';
    public const TASK_TYPE_IMPORT_CONTRACTOR_PRODS = 'import_contractor_prods';
    public const TASK_TYPE_UPDATE_ORDERS_STAT = 'update_orders_stat';
    public const TASK_TYPE_UPDATE_STORES_CACHE_FILES = 'update_stores_cache_files';

    public const TASK_TYPE_NAMES = [
        self::TASK_TYPE_UPDATE_PRODS_CACHE_FULL => 'Обновление кэша товаров - полное',
        self::TASK_TYPE_UPDATE_PRODS_CACHE_DYNAMIC => 'Обновление кэша товаров - динам.',
        self::TASK_TYPE_UPDATE_DATACACHE_SELECTED => 'Обновление отмеченных строк кэша',
        self::TASK_TYPE_CREATE_EXPORT_FILE => 'Генерация файла экспорта',
        self::TASK_TYPE_IMPORT_PROD_IMAGES => 'Импорт фото товаров',
        self::TASK_TYPE_CREATE_NOMENCLATURE_CONTROL_FILE => 'Генерация файла выгрузки номенклатуры',
        self::TASK_TYPE_IMPORT_NOMENCLATURE_CONTROL_FILE => 'Импорт файла выгрузки номенклатуры',
        self::TASK_TYPE_NOMENCLATURE_IMPORT_CONFIRM => 'Подтверждение импорта номенклатуры',
        self::TASK_TYPE_IMPORT_CONTRACTOR_PRODS => 'Импорт товаров поставщиков',
        self::TASK_TYPE_UPDATE_ORDERS_STAT => 'Обновление статистики заказов',
        self::TASK_TYPE_UPDATE_STORES_CACHE_FILES => 'Обновление файлов кэша магазинов',
    ];

    public const TASK_TYPE_DEFAULT = self::TASK_TYPE_UPDATE_PRODS_CACHE_DYNAMIC;

    public const TASKS_MAP = [
        self::TASK_TYPE_UPDATE_PRODS_CACHE_FULL => ProdsCacheUpdateFullTask::class,
        self::TASK_TYPE_UPDATE_PRODS_CACHE_DYNAMIC => ProdsCacheUpdateDynamicTask::class,
        self::TASK_TYPE_UPDATE_DATACACHE_SELECTED => ProdsCacheUpdateDatacacheSelectedTask::class,
        self::TASK_TYPE_CREATE_EXPORT_FILE => CreateExportFileTask::class,
        self::TASK_TYPE_IMPORT_PROD_IMAGES => ImportImagesTask::class,
        self::TASK_TYPE_CREATE_NOMENCLATURE_CONTROL_FILE => CreateNomenclatureControlFileTask::class,
        self::TASK_TYPE_IMPORT_NOMENCLATURE_CONTROL_FILE => ImportNomenclatureControlFileTask::class,
        self::TASK_TYPE_NOMENCLATURE_IMPORT_CONFIRM => NomenclatureImportConfirmTask::class,
        self::TASK_TYPE_IMPORT_CONTRACTOR_PRODS => ImportContractorProdsTask::class,
        self::TASK_TYPE_UPDATE_ORDERS_STAT => UpdateOrdersStatTask::class,
        self::TASK_TYPE_UPDATE_STORES_CACHE_FILES => UpdateStoresCacheFilesTask::class,
    ];

    public const SORT_TYPE_NAMES = [
        'by_priority_desc' => 'По приоритетности - от важных',
        'by_priority_asc' => 'По приоритетности - от неважных',
        'by_date_desc' => 'По дате - от новых',
        'by_date_asc' => 'По дате - от старых',
    ];

    public const SORT_TYPE_DEFAULT = 'by_priority_desc';
    public const PAGINATION_PAGE_RANGE = 5;


    public const PROD_IMPORT_STAT_STORAGE_FIELD = 'import_statuses';

    public const PROD_IMPORT_STATUS_IN_PROGRESS = 'in_progress';
    public const PROD_IMPORT_STATUS_WAIT_CONFIRM = 'wait_confirm';
    public const PROD_IMPORT_STATUS_CONFIRMATION = 'confirmation';
    public const PROD_IMPORT_STATUS_COMPLETED = 'completed';
    public const PROD_IMPORT_STATUS_NOT_COMPLETED = 'not_completed';
    public const PROD_IMPORT_STATUS_CANCELED = 'canceled';

    public const PROD_IMPORT_STATUS_MAP = [
        self::PROD_IMPORT_STATUS_IN_PROGRESS => 'Выполняется',
        self::PROD_IMPORT_STATUS_WAIT_CONFIRM => 'Ожидает подтверждения',
        self::PROD_IMPORT_STATUS_CONFIRMATION => 'Подтверждение выполняется',
        self::PROD_IMPORT_STATUS_COMPLETED => 'Выполнен',
        self::PROD_IMPORT_STATUS_NOT_COMPLETED => 'Не выполнен',
        self::PROD_IMPORT_STATUS_CANCELED => 'Отменен',
    ];

}