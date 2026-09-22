<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/2/28
 * Time: 上午11:07
 * comment:订单关闭类
 */

namespace app\frontend\modules\order\services\behavior;
use app\common\events\order\AfterOrderCanceledEvent;
use app\common\models\Order;


class OrderRestore extends ChangeStatusOperation
{
    protected $statusBeforeChange = [ORDER::WAIT_PAY,ORDER::WAIT_CONFIRM];
    protected $statusAfterChanged = ORDER::WAIT_PAY;
    protected $name = '恢复';
    protected $time_field = 'restore_time';
    protected $past_tense_class_name = 'OrderCanceled';

    public $params = [];
    /**
     * @return bool|void
     */
    protected function updateTable()
    {

        parent::updateTable();
    }
}