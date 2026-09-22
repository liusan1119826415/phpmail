<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/7/25
 * Time: 15:06
 */

namespace app\frontend\modules\payment\services\page;



use app\common\helpers\Url;
use app\common\models\Goods;
use app\common\models\Order;
use app\frontend\modules\payment\services\page\PaymentPageInterface;

class ShopPaymentPage extends PaymentPageInterface
{
    public function usable()
    {
        return true; //无对应订单类型按统一支付成功页显示
    }

    public function defaultDetailJumpLink()
    {
         //拼团订单 Url::absoluteApp('group/groupDetail/'.$this->getOrder()->id.'/groups')
        //酒店订单 Url::absoluteApp('hotel/orderdetail/'.$this->getOrder()->id.'/hotel')
        return Url::absoluteApp('member/orderdetail/'.$this->getOrder()->id.'/shop');
    }

    public function defaultDetailJumpMinLink()
    {
        return '/packageA/member/orderdetail/orderdetail?order_id='.$this->getOrder()->id.'&orderType=shop';
    }

    public function orderInfo()
    {
        return [];
    }


    public function goodsModule()
    {
        $goods_mode =  $this->configSet('goods_mode',0);

        switch ($goods_mode) {
            case 1:
                return $this->getAppointGoods();
                break;
            case 2:
                return $this->getCategoryGoods();
                break;
            default:
        }

        return $this->getRecommendGoods();

    }

    protected function getAppointGoods()
    {
        if (!$this->configSet('goods_ids', [])) {
            return [];
        }

        $selectRaw = [
            'yz_goods.id','yz_goods.title', 'yz_goods.thumb', 'yz_goods.plugin_id', 'yz_goods.stock',
            'yz_goods.price','yz_goods.market_price','yz_goods.cost_price','yz_goods.min_price','yz_goods.max_price',
        ];

        $list = $this->goodsModel()
            ->select($selectRaw)
            ->whereIn('yz_goods.id', $this->configSet('goods_ids', []))
            ->where('yz_goods.status', 1)
            ->orderBy('yz_goods.display_order','desc')->paginate(15);

        if ($list->isNotEmpty()) {
            $list->map(function ($goods) {
                $goods->thumb = yz_tomedia($goods->thumb);
            });
            return $list->toArray();
        }
        return [];
    }

    protected function getCategoryGoods()
    {

        $goods_category_ids = $this->configSet('goods_category_ids', []);


        if (!$goods_category_ids) {
            return [];
        }

        $selectRaw = [
            'yz_goods.id','yz_goods.title', 'yz_goods.thumb', 'yz_goods.plugin_id', 'yz_goods.stock',
            'yz_goods.price','yz_goods.market_price','yz_goods.cost_price','yz_goods.min_price','yz_goods.max_price',
        ];

        $list = $this->goodsModel()
            ->select($selectRaw)
            ->whereHas('hasManyGoodsCategory', function ($query) use ($goods_category_ids) {
                $query->whereIn('category_id', $goods_category_ids);
            })
            ->where('yz_goods.status', 1)
            ->orderBy('yz_goods.display_order','desc')->paginate(15);

        if ($list->isNotEmpty()) {
            $list->map(function ($goods) {
                $goods->thumb = yz_tomedia($goods->thumb);
            });
            return $list->toArray();
        }

        return [];
    }

    protected function getRecommendGoods()
    {

        $selectRaw = [
            'yz_goods.id','yz_goods.title', 'yz_goods.thumb', 'yz_goods.plugin_id', 'yz_goods.stock',
            'yz_goods.price','yz_goods.market_price','yz_goods.cost_price','yz_goods.min_price','yz_goods.max_price',
        ];

        $list = $this->goodsModel()
            ->select($selectRaw)
            ->where('yz_goods.is_recommand',1)
            ->where('yz_goods.status', 1)->orderBy('yz_goods.display_order','desc')->paginate(15);

        if ($list->isNotEmpty()) {
            $list->map(function ($goods) {
                $goods->thumb = yz_tomedia($goods->thumb);

            });

            return $list->toArray();
        }

        return [];
    }


    protected function goodsModel()
    {
        return Goods::uniacid()->whereInPluginIds();
    }
}