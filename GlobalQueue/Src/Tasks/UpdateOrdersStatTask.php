<?php

declare(strict_types=1);

namespace NutixApp\GlobalQueue\Src\Tasks;

use NutixApp\Core\Src\App;
use NutixApp\Core\Src\Db\NPDO;
use NutixApp\Core\Src\Exception\NutixException;
use NutixApp\GlobalQueue\GlobalQueue;
use NutixApp\GlobalQueue\Src\GlobalTaskFileStorage;
use NutixApp\Orders\Orders;

class UpdateOrdersStatTask extends GlobalTaskFileStorage
{


    public function __construct(int $id = 0, bool $onlyInfo = true)
    {
        $this->type = GlobalQueue::TASK_TYPE_UPDATE_ORDERS_STAT;
        $this->name = 'Обновление статистики заказов';
        $this->description = '';
        $this->priority = 2;

        parent::__construct($id, $onlyInfo);

        $this->limit = 500;
    }


    public function getDataChunk(int $offset, int $limit) : array
    {
        return array_slice($this->data, $offset, $limit);
    }


    public function getDataLength() : int 
    {
        return (is_array($this->data)) ? count($this->data) : 0;
    }


    /**
     * @throws NutixException
     */
    public function before(array $args = []) : void 
    {
        NPDO::lockTables([
            NPDO::$models->orders->tableName,
            NPDO::$models->ordersItems->tableName,
            NPDO::$models->ordersStatistics->tableName,
        ]);

        NPDO::$models->ordersStatistics->truncateTable();

        $statusesId = NPDO::$models->ordersStatuses->col(
            'SELECT `id` FROM %table%', [], 'int'
        );

        NPDO::startTransaction();

        try {

            foreach ($statusesId as $statusId) {

                if (in_array($statusId, [
                    Orders::ORDER_STATUS_CLOSED,
                    Orders::ORDER_STATUS_DECLINED,
                    Orders::ORDER_STATUS_ACCEPTEDRETURN,
                ])) continue;

                $ordersId = NPDO::$models->orders->col(
                    'SELECT `id` FROM %table% WHERE `status_id` = ?', [$statusId], 'int'
                );

                foreach ($ordersId as $id) {
                    NPDO::$models->ordersStatistics->addEmptyStat($id, $statusId);
                }
            }

        } catch (\Exception $e) {

            NPDO::rollbackTransaction();
            throw new NutixException('orders statistics update error', [], $e);
        }

        NPDO::commitTransaction();
    }


    public function after(array $args = []) : bool 
    {
        return true;
    }
    
}