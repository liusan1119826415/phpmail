<?php

namespace app\frontend\modules\coupon\services\models\TimeLimit;

use Carbon\Carbon;


class SinceReceiveCanUse extends TimeLimit
{
    public function valid()
    {
        if ($this->dbCoupon->time_days == false) {
            return true;
        }
        if (Carbon::createFromTimestamp($this->coupon->getMemberCoupon()->getRawOriginal('get_time'))->addDays($this->dbCoupon->time_days)->addSeconds($this->dbCoupon->wait_days*86400)->lte(Carbon::now())) {
            return false;
        }
        if (Carbon::createFromTimestamp($this->coupon->getMemberCoupon()->getRawOriginal('get_time'))->addSeconds($this->dbCoupon->wait_days*86400)->gte(Carbon::now())){
            return false;
        }
        return true;
    }

    private function receiveDays()
    {
        return $this->coupon->getMemberCoupon()->get_time->diffInDays();
    }

    public function expiredTime()
    {
        $time_end_time = $this->coupon->getMemberCoupon()->get_time->timestamp;
        if ($this->dbCoupon->time_days == 0) {
            $days = 9999;
        } else {
            $days = $this->dbCoupon->time_days;
        }
        $days+=$this->dbCoupon->wait_day;
        $d = date("Y-m-d", $time_end_time);
        return strtotime("{$d} +{$days} day");
    }
}
