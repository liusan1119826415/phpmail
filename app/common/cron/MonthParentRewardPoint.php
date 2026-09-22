<?php
/**
 * Created by PhpStorm.
 * User: CGOD
 * Date: 2019/12/18
 * Time: 16:00
 */

namespace app\common\cron;

use app\common\facades\Setting;
use app\common\models\UniAccount;
use app\common\services\point\ParentReward;
use Illuminate\Foundation\Bus\DispatchesJobs;

class MonthParentRewardPoint
{
    use DispatchesJobs;

    public function handle()
    {
        set_time_limit(0);
        $uniAccount = UniAccount::get() ?: [];
        foreach ($uniAccount as $u) {
            Setting::$uniqueAccountId = \YunShop::app()->uniacid = $u->uniacid;
            (new ParentReward())->award();
        }
    }
}