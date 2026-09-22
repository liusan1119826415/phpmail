<?php
/**
 * Created by PhpStorm.
 * User: yunzhong
 * Date: 2019/7/15
 * Time: 16:50
 */

namespace app\common\events\finance;


use app\common\events\Event;
use app\common\models\finance\BalanceRecharge;

class BalanceRechargedEvent extends Event
{
    public $rechargeModel;

    public function __construct($rechargeModel)
    {
        $this->rechargeModel = $rechargeModel;
    }

    /**
     * @return BalanceRecharge
     */
    public function getRechargeModel()
    {
        return $this->rechargeModel;
    }
}