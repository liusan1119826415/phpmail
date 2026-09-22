<?php

namespace app\common\cron;

use app\common\models\OrderGoods;
use Illuminate\Support\Facades\Redis;

class ClearWithholdStock
{
    public function handle()
    {
        $withholdZsetKeys = Redis::smembers('withhold_order_goods_id_keys');
        foreach ($withholdZsetKeys as $withholdZsetKey) {
            $orderGoodsIds = Redis::zrangeByScore($withholdZsetKey, 0, time() - 60);
            Redis::zrem($withholdZsetKey, 0, time() - 60);
            if ($orderGoodsIds) {
                $orderGoods = OrderGoods::whereIn('id', $orderGoodsIds)->get();
                foreach ($orderGoods as $aOrderGoods) {
                    /**
                     * @var OrderGoods $aOrderGoods
                     */
                    \Setting::$uniqueAccountId = \YunShop::app()->uniacid = $aOrderGoods->uniacid;

                    $aOrderGoods->goodsStock()->rollback();
                }
            }
        }
    }

}