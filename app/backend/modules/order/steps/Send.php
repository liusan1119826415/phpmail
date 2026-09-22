<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/12/9
 * Time: 15:15
 */

namespace app\backend\modules\order\steps;


use Illuminate\Support\Carbon;
class Send extends OrderStepFactory
{
    public function getTitle()
    {
        if (!$this->finishStatus()) {
            return __('order.wait_send');
        }
        return __('order.send_time');
    }

    public function getDescription()
    {
        if ($this->finishStatus()) {
            if (is_numeric($this->order->send_time)) {
                return Carbon::createFromTimestamp($this->order->send_time)->toDateTimeString();
            }
            return $this->order->send_time->toDateTimeString();
        }
        return parent::getDescription();
    }

    public function isShow()
    {
        return !($this->order->status == -1 && $this->order->send_time->toDateTimeString() == '1970-01-01 08:00:00');
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

        if ($this->order->send_time == "-") {
            return $this->order->send_time != "-";
        }
        return $this->order->send_time->toDateTimeString() != '1970-01-01 08:00:00';

    }

    public function sort()
    {
        return 20;
    }
}