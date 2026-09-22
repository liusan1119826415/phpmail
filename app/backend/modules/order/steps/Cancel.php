<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/12/9
 * Time: 16:40
 */

namespace app\backend\modules\order\steps;

use Illuminate\Support\Carbon;
class Cancel extends OrderStepFactory
{
    public function getTitle()
    {
        return __('order.cancel_time');
    }

    public function isShow()
    {
       return $this->order->status == -1;
    }


    public function getDescription()
    {
        if (is_numeric($this->order->cancel_time)) {
            return Carbon::createFromTimestamp($this->order->cancel_time)->toDateTimeString();
        }
        return $this->order->cancel_time->toDateTimeString();
    }

    public function getStatus()
    {
        return 'error';
    }

    public function waitStatus()
    {
        return false;
    }

    public function processStatus()
    {
        return false;
    }

    public function finishStatus()
    {
        return false;
    }


    public function sort()
    {
        return 99999;
    }
}