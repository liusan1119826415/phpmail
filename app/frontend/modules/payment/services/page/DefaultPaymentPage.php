<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/7/19
 * Time: 17:51
 */

namespace app\frontend\modules\payment\services\page;


use app\common\helpers\Url;
use app\common\models\Goods;
use app\common\models\Order;

class DefaultPaymentPage extends PaymentPageInterface
{
    public $code = 'shop';

    public $priority = 0;

    public function usable()
    {
        return true;
    }

    public function defaultDetailJumpLink()
    {
        return Url::absoluteApp('member/orderdetail/'.$this->getOrder()->id.'/shop');
    }

    public function defaultDetailJumpMinLink()
    {
        return  '/packageA/member/myOrder_v2/myOrder_v2';
    }

    public function topModule()
    {
        $data[] = [
            'name' => __('order.check_order'),
            'h5_link' => Url::absoluteApp('member/orderList/0'),
            'min_link' => '/packageA/member/myOrder_v2/myOrder_v2',
        ];

        $data[] = [
            'name' => __('order.return_index'),
            'h5_link' => Url::absoluteApp('home'),
            'min_link' => '/packageG/index/index',
        ];

        return $data;
    }

    public function orderInfo()
    {
      return [];
    }

    public function rewardModule()
    {
        return [];
    }

    public function carouselModule()
    {
        return [];
    }

    public function goodsModule()
    {

        $selectRaw = [
            'yz_goods.id','yz_goods.title', 'yz_goods.thumb', 'yz_goods.plugin_id', 'yz_goods.stock',
            'yz_goods.price','yz_goods.market_price','yz_goods.cost_price','yz_goods.min_price','yz_goods.max_price',
        ];

        $list = Goods::uniacid()->whereInPluginIds()
            ->select($selectRaw)
            ->where('yz_goods.is_recommand',1)->orderBy('yz_goods.display_order','desc')->paginate(15);

        if ($list->isNotEmpty()) {
            $list->map(function ($goods) {
                $goods->thumb = yz_tomedia($goods->thumb);

            });

            return $list->toArray();
        }

        return [];

    }

    public function openModuleKeys()
    {
        return array_keys($this->module);
    }

}
