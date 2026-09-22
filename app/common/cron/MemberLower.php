<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/31
 * Time: 15:49
 */

namespace app\common\cron;


use app\backend\modules\charts\modules\member\models\MemberLowerCount;
use app\backend\modules\charts\modules\member\models\MemberLowerGroupOrder;
use app\backend\modules\charts\modules\member\models\MemberLowerOrder;
use app\common\models\UniAccount;
use app\Jobs\MemberLowerCountJob;
use app\Jobs\MemberLowerGroupOrderJob;
use app\Jobs\MemberLowerOrderJob;


class MemberLower
{
    public function handle()
    {
        \Log::debug('----会员下线统计定时任务----');
        set_time_limit(0);
        $uniAccount = UniAccount::getEnable();
        ini_set('memory_limit', -1);
        (new MemberLowerCount())->truncate();
        (new MemberLowerOrder())->truncate();
        (new MemberLowerGroupOrder())->truncate();
        foreach ($uniAccount as $u) {
            \YunShop::app()->uniacid = $u->uniacid;
            \Setting::$uniqueAccountId = $u->uniacid;
            (new MemberLowerCountJob())->memberCount();
            (new MemberLowerOrderJob())->memberOrder();
            (new MemberLowerGroupOrderJob())->memberOrder();
        }
    }

}