<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/8/29
 * Time: 15:11
 */

namespace app\frontend\modules\coupon\controllers;

use app\common\components\ApiController;
use app\frontend\models\Goods;
use Illuminate\Support\Facades\DB;

class SearchController extends ApiController
{
    //放弃，在原接口上修改不出新接口
    public function goods()
    {

        $requestSearch = request()->input('search');

        $order_field = request()->input('order_field');
        if (!in_array($order_field, ['price', 'show_sales', 'comment_num', 'min_price', 'max_price'])) {
            $order_field = 'display_order';
        } else {
            if ($order_field == 'show_sales') {
                //排序改为虚拟销量
                $order_field = 'total_sales';
            }
        }
        $order_by = (request()->input('order_by') == 'asc') ? 'asc' : 'desc';

        if ($requestSearch) {
            $requestSearch = array_filter($requestSearch, function ($item) {
                return !empty($item) && $item !== 0  && $item !== "undefined";
            });
            $categorySearch = array_filter(\YunShop::request()->category, function ($item) {
                return !empty($item);
            });
            if ($categorySearch) {
                $requestSearch['category'] = $categorySearch;
            }
        }

        $goods_model = Goods::uniacid();

        //增加默认搜索不隐藏的商品
        $requestSearch['is_hide'] = 1;
        $ims = DB::getTablePrefix();
        $goods_select = $ims . 'yz_goods.has_option,' . $ims . "yz_goods.id,thumb,plugin_id,real_sales+virtual_sales total_sales,market_price,price,min_price,max_price,cost_price,title," . $ims . "yz_goods.stock, " . $ims . "yz_goods.id as goods_id";
        $option_select = 'goods_id,product_price,market_price,stock,cost_price';
        $build = $goods_model->search($requestSearch)
            ->selectRaw($goods_select)
            ->with(['hasManyOptions' => function ($query) use ($option_select) {
                $query->selectRaw($option_select);
            }])
            ->where("yz_goods.status", 1);


        $list = $build->orderBy($order_field, $order_by)
            ->orderBy('yz_goods.id', 'desc')
            ->paginate(20);


        $list->map(function ($item) {
            $item->thumb = yz_tomedia($item->thumb);

            //会员vip价格
            $item->vip_price = $item->vip_price;
            $item->vip_next_price = $item->vip_next_price;
            $item->vip_level_status = $item->vip_level_status;
            $item->price_level = $item->price_level;
            $item->is_open_micro = $item->is_open_micro;


            return $item;
        });

        $list = $list->toArray();
        foreach ($list['data'] as &$v){
            if ($v['has_option'] && $v['has_many_options']){
                $v['stock'] = array_sum(array_column($v['has_many_options'],'stock'));
            }
        }

        return $this->successJson('成功', $list);
    }

    protected function getGoodsModel()
    {
        $goods_model = \app\common\modules\shop\ShopConfig::current()->get('goods.models.commodity_classification');
        return new $goods_model;
    }
}