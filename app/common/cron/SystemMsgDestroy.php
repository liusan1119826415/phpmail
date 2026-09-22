<?php

namespace app\common\cron;

use app\common\facades\Setting;
use app\common\models\systemMsg\SysMsgLog;
use app\common\models\UniAccount;
use Illuminate\Support\Carbon;

class SystemMsgDestroy
{
    public function handle()
    {
        try {
            set_time_limit(0);
            $uniAccount = UniAccount::get() ?: [];
            $time = Carbon::now()->subMonths(3)->startOfDay()->timestamp;
            foreach ($uniAccount as $u) {
                Setting::$uniqueAccountId = \YunShop::app()->uniacid = $u->uniacid;
                SysMsgLog::uniacid()->where('created_at', '<', $time)->delete();
            }
        } catch (\Exception $e) {
            \Log::debug('-----定时删除系统消息错误------', [$e->getMessage()]);
        }
    }
}