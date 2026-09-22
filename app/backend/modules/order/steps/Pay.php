<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/12/9
 * Time: 15:14
 */

namespace app\backend\modules\order\steps;


use Illuminate\Support\Carbon;
class Pay extends OrderStepFactory
{
    public function getTitle()
    {
        if (!$this->finishStatus()) {
            return __('order.wait_pay');
        }

        return __('order.pay_time');
    }

    public function getDescription()
    {
        if ($this->finishStatus()) {
            $payTime = $this->order->pay_time;

            // 判断是否为时间戳（整数格式）
            if (is_numeric($payTime)) {
                return Carbon::createFromTimestamp($payTime)->toDateTimeString();
            }

            // 如果是 Carbon/DateTime 对象，直接转换
            return Carbon::parse($payTime)->toDateTimeString();
        }

        return parent::getDescription();
    }

    public function getStatus()
    {
        if ($this->finishStatus()) {
            return 'finish';
        } elseif ($this->processStatus()) {
            return 'process';
        } elseif ($this->waitStatus()) {
            return 'wait';
        }

        return 'error';
    }

    public function isShow()
    {
        return !($this->order->status == -1 &&  $this->order->pay_time->toDateTimeString() == '1970-01-01 08:00:00');
    }

    public function waitStatus()
    {
        return $this->order->status < 1;
    }

    public function processStatus()
    {
        return $this->order->status == 1;
    }

    public function finishStatus()
    {
       //
        if (is_numeric($this->order->pay_time)) {
            return $this->order->pay_time != 0;
        }
        return  $this->order->pay_time->toDateTimeString() !='1970-01-01 08:00:00';

    }

    public function sort()
    {
        return 10;
    }
}