<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/12/9
 * Time: 15:12
 */

namespace app\backend\modules\order\steps;

use Illuminate\Support\Carbon;
class Create extends OrderStepFactory
{
    public function getTitle()
    {
        return __('order.order_time');
    }

    public function getDescription()
    {
        if ($this->finishStatus()) {
            if (is_numeric($this->order->create_time)) {
                return Carbon::createFromTimestamp($this->order->create_time)->toDateTimeString();
            }
            return $this->order->create_time->toDateTimeString();
        }
        return parent::getDescription();
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
       return true;
    }


    public function sort()
    {
        return 0;
    }


}