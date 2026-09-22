<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/12/9
 * Time: 15:16
 */

namespace app\backend\modules\order\steps;


use Illuminate\Support\Carbon;
class Receive extends OrderStepFactory
{
    public function getTitle()
    {
        if (!$this->finishStatus()) {
            return __('order.wait_receive');
        }

        return __('order.finish');
    }

    public function getDescription()
    {
        if ($this->finishStatus()) {
            if (is_numeric($this->order->finish_time)) {
                return Carbon::createFromTimestamp($this->order->finish_time)->toDateTimeString();
            }
            return $this->order->finish_time->toDateTimeString();
        }
        return parent::getDescription();
    }

    public function isShow()
    {
        return !($this->order->status == -1 &&  $this->order->finish_time->toDateTimeString() == '1970-01-01 08:00:00');
    }

    public function waitStatus()
    {
        return $this->order->status < 2;
    }

    public function processStatus()
    {
        return $this->order->status == 2;
    }

    public function finishStatus()
    {
        if (is_numeric($this->order->finish_time) || $this->order->finish_time == "-") {
            return $this->order->finish_time != 0;
        }
        return  $this->order->finish_time->toDateTimeString() !='1970-01-01 08:00:00';
    }

    public function sort()
    {
        return 30;
    }
}