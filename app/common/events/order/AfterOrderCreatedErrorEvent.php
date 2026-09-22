<?php
/**
 * 订单发货后事件
 * Created by PhpStorm.
 * Author:
 * Date: 2017/3/3
 * Time: 上午11:44
 */

namespace app\common\events\order;

use app\common\events\Event;

class AfterOrderCreatedErrorEvent extends Event
{
    public $orders;
    public $logs = [];

    public function __construct($orders)
    {
        $this->orders = $orders;
    }

    public function getOrders()
    {
        return $this->orders;
    }

    public function pushLogs($log)
    {
        $this->logs[] = $log;
    }

    public function getLogs()
    {
        return $this->logs;
    }


    public function saveLogs()
    {
        if ($logs = $this->getLogs()) {
            foreach ($logs as $log) {
                $log->save();
            }
        }
    }

}