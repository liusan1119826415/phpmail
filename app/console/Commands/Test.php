<?php

namespace app\Console\Commands;


use app\common\facades\SiteSetting;
use app\common\models\Member;
use app\common\models\MemberShopInfo;
use app\common\models\Order;
use app\common\models\OrderRequest;
use app\common\modules\shop\ShopConfig;
use app\framework\Cron\Cron;
use app\host\HostManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Yunshop\AreaDividend\models\AreaDividendAgent;
use function GuzzleHttp\Psr7\build_query;
use function GuzzleHttp\Psr7\parse_query;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


class Test extends Command
{
    use \Illuminate\Foundation\Bus\DispatchesJobs;

    protected $signature = 'test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '测试';


    /**
     * Execute the console command.
     */
    public function handle()
    {


        //$this->mqttTest();
        $a = storage_path('app/cache/qr/store-oil-station/outside/');
        echo $a.PHP_EOL;
        echo 'test'.PHP_EOL;
    }


    public function mqttTest()
    {

        $worker = new \app\worker\Worker();
        $worker->name = 'yun_shop_mqtt';


        $mqttKeeper = new \app\process\MqttKeeper($worker,SiteSetting::get('mqtt'));

        // 进程启动后的回调
        $worker->onWorkerStart = [$mqttKeeper, 'test'];

        // 关闭队列进程
        $worker->onWorkerStop = [$mqttKeeper, 'onWorkerStop'];

        // 运行worker
        \app\worker\Worker::runAll();
    }


    private function teamMembers($uids, $level = null)
    {
        if (isset($level)) {
            $level--;
        }

        if (!is_array($uids)) {
            $uids = [$uids];
        }
        $children = Redis::hmget('children:' . \YunShop::app()->uniacid, $uids);

        $result = [];
        foreach ($children as $child) {
            if (isset($child)) {
                $childUids = explode(',', $child);
                if ($childUids) {
                    $result = array_merge($result, $childUids);
                }
            }
        }
        if ($level === 0) {
            return $result;
        }
        if ($result) {
            $result = array_merge($result, $this->teamMembers($result, $level));
        }

        return $result;
    }

    private function data()
    {
        app()->db->enableQueryLog();
        \YunShop::app()->uniacid = 2;
        $members = MemberShopInfo::getQuery()->select(DB::raw('member_id as `0`'), DB::raw('parent_id as `1`'))->where('parent_id', '!=', 0)->get();
        $this->parents($members);
        $this->children($members);

    }

    private function parents($members)
    {
        $parents = [];
        foreach ($members as $member) {
            $parents[$member[0]] = $member[1];
        }
        Redis::del('parents:' . \YunShop::app()->uniacid);
        Redis::hmset('parents:' . \YunShop::app()->uniacid, $parents);
    }

    private function children($members)
    {
        $childrenMap = [];
        foreach ($members as $member) {
            $childrenMap[$member[1]][] = $member[0];
        }

        foreach ($childrenMap as &$children) {
            $children = implode(',', $children);
        }
        Redis::del('children:' . \YunShop::app()->uniacid);
        Redis::hmset('children:' . \YunShop::app()->uniacid, $childrenMap);
    }
}
