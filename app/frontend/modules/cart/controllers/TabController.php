<?php

namespace app\frontend\modules\cart\controllers;

use app\common\components\ApiController;

class TabController extends ApiController
{
    public function index()
    {
        $data = collect();

        if (app('plugins')->isEnabled('store-cashier') && \Setting::get('plugin.store-cart.is_open')) {
            $data->push([
                'plugin_id' => STORE_CART_PLUGIN_ID,
                'plugin_name' => STORE_CART_PLUGIN_NAME,
                'plugin_key' => 'store-cart',
                'tab_name' => '门店购物车',
                'url' => [
                    'index' => 'plugin.store-cart.frontend.controllers.cart.list',//首页
                    'modify' => 'plugin.store-cart.frontend.controllers.cart.list.updateNumV2',//修改商品数量
                    'destroy' => 'plugin.store-cart.frontend.controllers.cart.list.destroy',//删除
                    'del_failure' => 'plugin.store-cart.frontend.controllers.cart.list.del-failure-cart',//删除失效
                ],
                'sort' => 1,//排序
                'multi_selection' => false,//是否多选
            ]);
        }
        $data = $data->sortByDesc('sort');
        $data->prepend([
            'plugin_id' => 0,
            'plugin_name' => \Setting::get('shop.shop.name') ?: '商城',
            'plugin_key' => 'shop',
            'tab_name' => '商城购物车',
            'sort' => 0,
            'multi_selection' => true,
            'url' => [
                'index' => 'cart.list.index',
                'modify' => 'cart.list.updateNumV2',
                'destroy' => 'cart.list.destroy',
                'del_failure' => 'cart.list.del-failure-cart',
            ]
        ]);

        return $this->successJson('ok', $data);
    }
}
