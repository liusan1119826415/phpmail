<?php

namespace app\outside\modules\order\controllers;

use app\common\helpers\ArrayHelper;
use app\outside\controllers\OutsideController;
use app\outside\modules\order\models\Order;

class ListController  extends OutsideController
{
    public function index()
    {
        $create_time = request()->input('create_time');
        $uid = request()->input('uid');
        $plugin_type = request()->input('plugin_type');//插件订单
        $plugin_id = explode(",", $plugin_type);


        $order = Order::uniacid()->select(['id','order_sn','yz_order.uid','price','create_time','mc_members.nickname'])
            ->join('mc_members', 'mc_members.uid', '=', 'yz_order.uid')
            ->with(['orderGoods' => function($q) {
                $q->select(['title','order_id','goods_price']);
            }]);

        if (isset($plugin_id)) {
            $plugin_id[] = '0';
            $order = $order->whereIn('plugin_id', $plugin_id);
        } else {
            $order = $order->where('plugin_id', 0);
        }
        if (isset($uid)) {
            $order = $order->where('yz_order.uid', $uid);
        }

        if (isset($create_time)) {
            $order = $order->where('create_time', '>=',$create_time);
        }
        $order = $order->get();
        if ($order->isEmpty()) {
            $this->errorJson('订单不存在');
        }


        return $this->successJson('成功', $order);

    }

    public function page()
    {
        $search = [
            'create_time' => request()->input('create_time'),
            'uid' => request()->input('uid'),
            'mobile' => request()->input('mobile'),
            'contact_phone' => request()->input('contact_phone'),
            'plugin_ids' => ArrayHelper::unreliableDataToArray(request()->input('plugin_type',0)),
        ];

        $page = Order::getList($search)->orderBy('yz_order.create_time', 'desc')
            ->paginate(15);

        $array = array_only($page->toArray(),['total','current_page','data','per_page','last_page']);

        return $this->successJson('list', $array);
    }

}