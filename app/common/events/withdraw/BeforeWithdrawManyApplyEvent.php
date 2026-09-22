<?php
/**
 * Created by PhpStorm.
 *
 * User: king/QQ：995265288
 * Date: 2018/6/5 下午3:37
 * Email: livsyitian@163.com
 */

namespace app\common\events\withdraw;


use app\common\events\Event;
use app\common\events\WithdrawEvent;

//提现前事件(多条)
class BeforeWithdrawManyApplyEvent extends Event
{
    private $uid;
    private $withdraw_data;
    private $amount;
    private $pay_way;
    private $poundage;

    public function __construct($amount, $pay_way, $poundage, $withdraw_data, $uid)
    {
        $this->uid = $uid;
        $this->withdraw_data = $withdraw_data;
        $this->amount = $amount;
        $this->pay_way = $pay_way;
        $this->poundage = $poundage;
    }

    public function getUid()
    {
        return $this->uid;
    }

    /**
     * 提现数据（多条）
     * @return mixed
     */
    public function getWithdrawData()
    {
        return $this->withdraw_data;
    }

    /**
     * 提现总金额
     * @return mixed
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * 提现方式
     * @return mixed
     */
    public function getPayWay()
    {
        return $this->pay_way;
    }

    /**
     * 提现手续费
     * @return mixed
     */
    public function getPoundage()
    {
        return $this->poundage;
    }
}
