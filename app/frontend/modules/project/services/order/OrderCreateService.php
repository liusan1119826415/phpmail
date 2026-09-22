<?php

namespace app\frontend\modules\project\services\order;

use app\common\exceptions\AppException;
use app\common\models\OrderAddress;
use app\frontend\models\Member;
use app\frontend\modules\cart\models\MemberCart;
use app\frontend\modules\memberCart\MemberCartCollection;
use app\frontend\modules\project\models\Project;
use app\common\models\Order;
use Illuminate\Support\Facades\Redis;

/**
 * 订单创建服务
 * 负责：下单前校验、预下单、正式下单、下单成功页
 */
class OrderCreateService
{
    /**
     * 下单前校验（检查下架商品、无效选项）
     */
    public function verifyBeforeOrder(int $project_id): array
    {
        $project = Project::find($project_id);
        if ($project->order_status == 1) {
            throw new AppException('订单已经生成');
        }

        $cartItems = MemberCart::where('project_id', $project_id)
            ->with(['goods' => function ($query) {
                $query->withTrashed()->select('id', 'status', 'title');
            }, 'goodsOption' => function ($query) {
                $query->select('id', 'title');
            }])
            ->get();

        if ($cartItems->isEmpty()) {
            throw new AppException('项目还未配置商品数据');
        }

        $offShelfGoods = [];
        $invalidOptions = [];

        foreach ($cartItems as $item) {
            if ($item->goods && $item->goods->status == 0) {
                $offShelfGoods[] = [
                    'id' => $item->id,
                    'floor_id' => $item->floor_id,
                    'space_id' => $item->space_id,
                    'goods_id' => $item->goods_id,
                    'goods_title' => $item->goods->title
                ];
            }

            if ($item->option_id && !$item->goodsOption) {
                $invalidOptions[] = [
                    'id' => $item->id,
                    'floor_id' => $item->floor_id,
                    'space_id' => $item->space_id,
                    'goods_id' => $item->goods_id,
                    'option_id' => $item->option_id,
                    'goods_title' => $item->goods ? $item->goods->title : '未知商品'
                ];
            }
        }

        if (!empty($invalidOptions)) {
            $errorMsg = '以下商品选项无效或已删除：';
            foreach ($invalidOptions as $option) {
                $errorMsg .= "【商品:{$option['goods_title']} 选项ID:{$option['option_id']}】";
            }
            $errorMsg .= "，请修正后再下单";
            return [
                'status' => 0,
                'offShelf' => 2,
                'offShelfGoods' => $invalidOptions,
                'msg' => $errorMsg
            ];
        }

        if (!empty($offShelfGoods)) {
            $errorMsg = '以下商品已下架：';
            foreach ($offShelfGoods as $goods) {
                $errorMsg .= "【ID:{$goods['goods_id']} {$goods['goods_title']}】";
            }
            $errorMsg .= "，请替换或删除再下单";
            return [
                'status' => 0,
                'offShelf' => 1,
                'offShelfGoods' => $offShelfGoods,
                'msg' => $errorMsg
            ];
        }

        return ['status' => 1];
    }

    /**
     * 预下单（获取订单预览数据）
     */
    public function preOrder(int $project_id)
    {
        $project = Project::find($project_id);
        if ($project->order_status == 1) {
            throw new AppException('订单已经生成');
        }
        $trade = $this->getMemberCarts($project_id)->getTrade(Member::current());
        $trade->total_num = app('OrderManager')->make('MemberCart')->where('project_id', $project_id)->sum('total');
        return $trade;
    }

    /**
     * 正式下单
     */
    public function createOrder(int $project_id)
    {
        $project = Project::find($project_id);
        if ($project->order_status == 1) {
            throw new AppException('订单已经生成');
        }
        $trade = $this->getMemberCarts($project_id)->getTrade(Member::current());
        $orderId = $trade->generate();

        $project->order_status = 1;
        $project->order_id = $orderId;
        $project->save();

        $member_id = \YunShop::app()->getMemberId();
        Redis::del("user:{$member_id}:active_project_1");

        return ['order_ids' => $orderId];
    }

    /**
     * 下单成功页数据
     */
    public function submitOrderSuccess(int $order_id): array
    {
        $order = Order::with(['project' => function ($query) {
            $query->select("id", "name");
        }])->find($order_id);

        if (!$order) {
            throw new AppException("订单不存在");
        }

        $order_address = OrderAddress::where('order_main_id', $order->id)->first();
        $customer_service_phone = \Setting::get('shop.trade.customer_service_phone');

        return [
            'id' => $order->id,
            "order_sn" => $order->order_sn,
            "goods_total" => $order->goods_total,
            "goods_price" => $order->goods_price,
            "customer_service_phone" => $customer_service_phone,
            "project_name" => $order->project->name,
            "address" => $order_address->address,
            "consignee" => $order_address->realname . " " . $order_address->mobile
        ];
    }

    /**
     * 获取项目购物车集合
     */
    protected function getMemberCarts($project_id)
    {
        static $memberCarts;
        if (!isset($memberCarts)) {
            $memberCarts = app('OrderManager')->make('MemberCart')->where('project_id', $project_id)->get();
            $memberCarts = new MemberCartCollection($memberCarts);
            $memberCarts->loadRelations();
        }

        if ($memberCarts->isEmpty()) {
            throw new AppException('项目没有产品，请前往产品库选择产品');
        }
        return $memberCarts;
    }
}
