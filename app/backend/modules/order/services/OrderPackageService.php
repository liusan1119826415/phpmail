<?php

namespace app\backend\modules\order\services;

use app\common\exceptions\AppException;
use app\common\exceptions\ShopException;
use app\common\models\order\OrderPackage;
use app\frontend\models\OrderGoods;
use Illuminate\Support\Collection;

class OrderPackageService
{

    /**
     * 兼容之前哪些人偷懒造成的参数不统一问题
     * @param $requestData
     * @param null $orderGoodsCollection
     * @return array
     */
    public function unifyFormat($requestData,$orderGoodsCollection = null)
    {

        //是否多包裹发货 0否1是
        $requestData['need_trigger_package_event'] = 0;

        is_null($orderGoodsCollection) && $orderGoodsCollection = $this->canSendGoods($requestData['order_id']);

        //参数优先 order_package > order_goods_ids
        //order_package参数不为空说明是正常多包裹发货
        if (!empty($requestData['order_package'])) {

            $requestData['need_trigger_package_event'] = 1;

            return $requestData;
        }

        if (!empty($requestData['order_goods_ids'])) {

            $requestData['need_trigger_package_event'] = 1;

            $requestData['order_package'] = $orderGoodsCollection->whereIn('id', $requestData['order_goods_ids'])->map(function (OrderGoods $orderGoods) {
                $not_number = $orderGoods->getRefundTotal() + $orderGoods->expressPackage->sum('total');
                $orderGoods->origin_total = $orderGoods->total;
                $orderGoods->total =  $orderGoods->origin_total - $not_number;
                if ($orderGoods->total > 0) {
                    return ['order_goods_id' => $orderGoods->id, 'total' =>  $orderGoods->total];
                }
                return null;
            })->filter()->values()->toArray();

        } else {

            $requestData['order_package'] = $orderGoodsCollection->map(function ($orderGoods) {
                return ['order_goods_id' => $orderGoods->id, 'total' =>  $orderGoods->total];
            })->values()->toArray();
        }

        return $requestData;
    }

    /**
     * @param Collection $orderGoodsCollection
     * @param Collection $orderPackageCollect
     */
    public function validateNum(Collection $orderGoodsCollection, Collection $orderPackageCollect)
    {
        $orderPackageCollect->each(function ($item) use ($orderGoodsCollection) {
            $goods = $orderGoodsCollection->firstWhere('id', $item['order_goods_id']);
            if (!$goods) {
                throw new ShopException('商品id:' . $item['order_goods_id'] . '不存在或已全部发货');
            }
            $num = $goods->sum('total') - $item['total'];
            if ($num < 0) {
                throw new ShopException('商品id:' . $item['order_goods_id'] . '超发数量' . abs($num));
            }
        });
    }

    /**
     * 返回当前订单可以正常发货的订单商品
     * @param $order_id
     * @return \app\framework\Database\Eloquent\Collection
     */
    public function canSendGoods($order_id)
    {
        $orderGoodsCollection = OrderGoods::uniacid()
            ->where('order_id',$order_id)
            ->with(['expressPackage','manyRefundedGoodsLog'])->get();

        $order_goods = $orderGoodsCollection->map(function (OrderGoods $orderGoods) {

            $not_number = $orderGoods->getRefundTotal() + $orderGoods->expressPackage->sum('total');
            $orderGoods->origin_total = $orderGoods->total;
            $orderGoods->total =  $orderGoods->origin_total - $not_number;

            return $orderGoods;
        })->filter(function ($orderGoods) {
            return $orderGoods->total > 0;
        })->values();

        return $order_goods;
    }


    /**
     * 订单编辑修改物流，如果物流不存在的新增物流包裹
     * @param int $order_id
     * @param int $express_id
     */
    public static function absentAdd($order_id,$express_id)
    {
        $orderGoodsCollection = (new OrderPackageService())->canSendGoods($order_id);
        $order_package = $orderGoodsCollection->map(function ($orderGoods) {
            return ['order_goods_id' => $orderGoods->id, 'total' =>  $orderGoods->total];
        })->values()->toArray();

        self::saveOneOrderPackage($order_id, $express_id, $order_package);
    }

    /**
     * 过滤商品数量
     * @param Collection $orderGoodsCollect
     * @param Collection $orderPackageCollect
     * @return Collection
     */
    public static function filterGoods(Collection $orderGoodsCollect, Collection $orderPackageCollect)
    {
        if ($orderGoodsCollect->isEmpty()) {
            return $orderGoodsCollect;
        }

        if ($orderPackageCollect->isEmpty()) {
            return $orderGoodsCollect;
        }

        return $orderGoodsCollect->filter(function (&$item, $key) use ($orderPackageCollect) {
            $item['total'] -= $orderPackageCollect->where('order_id', $item['order_id'])
                ->where('order_goods_id', $item['id'])
                ->sum('total');
            if ($item['total'] <= 0) {
                return false;
            }
            return true;
        });
    }

    /**
     * 校验单包裹商品
     * @param Collection $orderPackageCollect
     * @param Collection $orderGoodsCollect
     * @return bool
     * @throws AppException
     */
    public static function checkGoodsPackage(Collection $orderPackageCollect, Collection $orderGoodsCollect)
    {
        $orderPackageCollect->each(function (&$item, $key) use ($orderPackageCollect, $orderGoodsCollect) {
            $goods = $orderGoodsCollect->where('id', $item['order_goods_id']);
            if ($goods->isEmpty()) {
                throw new ShopException('商品id:' . $item['order_goods_id'] . '不存在或已全部发货');
            }
            $num = $goods->sum('total') - $orderPackageCollect->where('order_goods_id', $item['order_goods_id'])->sum('total');
            if ($num < 0) {
                throw new ShopException('商品id:' . $item['order_goods_id'] . '超发数量' . abs($num));
            }
        });

        return true;
    }

    /**
     * 单包裹发货(一个物流)
     * @param int $order_id
     * @param int $express_id
     * @param Collection $orderGoodsCollect
     * @return \app\common\models\BaseModel|false
     */
    public static function saveOneOrderPackage(int $order_id, int $express_id, $orderGoods)
    {
        if (!$order_id or !$express_id) {
            return false;
        }
        $data = array();
        foreach ($orderGoods as $v) {
            $data[] = [
                'uniacid' => \YunShop::app()->uniacid,
                'order_id' => $order_id,
                'order_goods_id' => $v['order_goods_id'] ?: $v['id'],
                'total' => $v['total'],
                'order_express_id' => $express_id,
                'created_at' => time()
            ];
        }
        
        return OrderPackage::insert($data);
    }

    /**
     * 获取剩余未发货的商品
     * @param $order_id
     * @return Collection
     */
    public static function getNotDeliverGoods($order_id)
    {
        $order_goods = OrderGoods::uniacid()->where('order_id',$order_id)->whereNull('order_express_id')->get()->makeVisible('order_id');
        $order_package = OrderPackage::getOrderPackage($order_id)->where('order_express_id','!=',false);
        return static::filterGoods($order_goods,$order_package);
    }
}