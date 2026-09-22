<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2021/3/4
 * Time: 13:58
 */

namespace app\backend\modules\order\controllers;


use app\backend\modules\order\models\VueOrder;
use Darabonba\GatewaySpi\Models\InterceptorContext\request;

class ShopOrderListController extends OrderListController
{
    protected function getOrder()
    {
        return VueOrder::pluginId();
    }

    protected function mergeExtraData()
    {
        $data = [
            'listUrl' => yzWebFullUrl('order.shop-order-list.get-list'), //订单查询路由
            'exportUrl' => 'order.shop-order-list.shop-export',
        ];

        return $data;
    }

    public function shopExport()
    {
        $search = request()->input('export_search') ?: [];
        $search['search']['plugin_id'] = 0;
        request()->offsetSet('export_search', $search);
        return $this->export();
    }

    public function index()
    {

        return view('order.vue-list', $this->getData())->render();
    }

}
