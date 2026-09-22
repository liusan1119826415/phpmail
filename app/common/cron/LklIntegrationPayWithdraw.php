<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/12/9
 * Time: 12:58
 */

namespace app\common\cron;

use app\backend\modules\integrationPay\models\IntegrationPayWithdraw;
use app\common\facades\Setting;
use app\common\models\UniAccount;
use app\frontend\models\MemberShopInfo;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\DB;

class LklIntegrationPayWithdraw
{
    use DispatchesJobs;

    public $memberSet;
    public $setLog;

    public $uniacid;

    public function handle()
    {
        $uniAccount = UniAccount::getEnable();
        foreach ($uniAccount as $u) {
            \YunShop::app()->uniacid = $u->uniacid;
            Setting::$uniqueAccountId = $u->uniacid;
            $this->uniacid = $u->uniacid;


            $this->runTask();
        }
    }

    public function runTask()
    {

        $list = IntegrationPayWithdraw::whereIn('draw_status',[0,2])->get();


        if ($list->isEmpty()) {
            return;
        }

        try {
            $client = new \Yunshop\ServiceProviderConnect\common\ClientRequest();
            $client->setMethod('');
        } catch (\Exception $e) {
            \Log::debug('<<----定时获取拉卡拉聚合支付提现状态失败----'.$e->getMessage());
        }

        foreach ($list as $item) {

            $client->setParameter('draw_jnl', $list->withdraw_sn);
            $result = $client->post();

            if ($result['status']) {
                $item->draw_status = $result['data']['status'];
                $item->save();
            }
        }
    }
}