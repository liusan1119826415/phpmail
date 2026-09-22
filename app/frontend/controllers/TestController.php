<?php

namespace app\frontend\controllers;

use app\common\components\BaseController;
use app\common\events\order\AfterOrderCloseEvent;
use app\common\events\order\AfterOrderPaidEvent;
use app\common\exceptions\AppException;
use app\common\facades\Setting;
use app\common\models\Member;
use app\common\models\MemberShopInfo;
use app\common\models\Option;
use app\common\models\Order;
use app\common\models\OrderGoods;
use app\common\modules\goods\GoodsRepository;
use app\common\modules\option\OptionRepository;
use app\frontend\models\Goods;
use app\frontend\modules\order\services\OrderService;
use Illuminate\Cache\CacheManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Yunshop\Love\Modules\Goods\GoodsLoveRepository;
use Yunshop\PackFixedPrice\api\CartBuyController;
use Yunshop\PackFixedPrice\api\CheckoutController;
use function EasyWeChat\Payment\get_client_ip;

class TestController extends BaseController
{
    public function index()
    {
        $_GET['cart_ids'] = '1,2';
        $a = (new CartBuyController())->index();
        echo ($a->getContent());
        exit();
        dump_logs();
        $member = MemberShopInfo::find(698250);
        $member->update(['inviter'=>0]);
        DB::transaction(function () {

            $member = MemberShopInfo::find(698250);
            $parent = MemberShopInfo::find(345881);
            dump($member->parent_id,$member->parent_id);

            Member::chkAgent($member->member_id, $parent->member_id);
            dd($member->getDirty());
        });

    }

    public function concurrent()
    {
        $sn = OrderService::createOrderSN();

        \Illuminate\Support\Facades\Redis::RPUSH('order_sn_test', $sn);
        echo $sn; exit();
    }

    public function ttt()
    {

        $del = request()->input('del');

        $array = \Illuminate\Support\Facades\Redis::LRANGE('order_sn_test', 0,-1);
        $unique_arr = array_unique($array);
        $repeat_arr = array_diff_assoc($array,$unique_arr);
        $num = count($repeat_arr);

        $msg = '';
        if (isset($del)) {
            $a = \Illuminate\Support\Facades\Redis::del('order_sn_test');
            $msg = '已删除统计订单号redis记录';
        }

        echo "重复订单号数量：{$num}<br/>{$msg}"; exit();

    }
}