<?php
/**
 * Created by PhpStorm.
 * User: blank
 * Date: 2022/11/3
 * Time: 15:51
 */

namespace app\common\modules\refund\product;

use app\backend\modules\goods\models\GoodsTradeSet;
use app\common\exceptions\AppException;
use app\backend\modules\goods\models\ReturnAddress;
use app\common\models\Order;
use app\common\models\refund\RefundApply;
use app\common\models\refund\RefundGoodsLog;
use app\common\modules\refund\optional\OptionalTypesTrait;
use Illuminate\Support\Carbon;

abstract class RefundOrderTypeBase
{
    use OptionalTypesTrait;

    /**
     * @var Order
     */
    protected $order;


    /**
     * @var RefundApply
     */
    protected $refundApply;

    /**
     * @var \app\framework\Database\Eloquent\Collection
     */
    protected $refundedCollect;

    /**
     * @var string 区别前端还是后端
     */
    protected $port;

    public function __construct(Order $order, $port = 'frontend')
    {
        $this->order = $order;

        $this->port = $port;
    }

    final public function setRefundApply(RefundApply $refundApply)
    {
        $this->refundApply = $refundApply;
    }

    /**
     * @return Order
     */
    final public function getOrder()
    {
        return $this->order;
    }

    final public function frontendFormatArray()
    {

        $order = $this->formatOrder();
        $order['order_goods'] = $this->canApplyRefundGoods();

        return $order;
    }


    /**
     * 创建售后申请,让对应的插件进行保存前最后的业务处理
     * todo 需要注意：在这里对 $refundApply 的属性进行修改是会影响到最终保存的数据的
     * @param RefundApply $refundApply
     */
    public function handleAfterSales(RefundApply $refundApply)
    {

    }


    /**
     * 创建售后申请，部分退款标识
     * @param $applyRefundGoods ['id', 'total', 'refund_price']
     * @param $request \Illuminate\Http\Request|string
     * @return int
     */
    public function applyPratRefundStatus($applyRefundGoods,$request)
    {

        if ($request->input('refund_type') == RefundApply::REFUND_TYPE_EXCHANGE_GOODS) {
            return 0;
        }

        if (empty($applyRefundGoods)) {
            return 0;
        }

        //订单商品总数量
        $orderGoodsNum = $this->order->orderGoods->sum('total');

        //本次申请售后商品总数量
        $currentApplyNum = array_sum(array_column($applyRefundGoods, 'total'));


        //一次性申请全部商品退款
        if ($orderGoodsNum == $currentApplyNum) {
            return 3; //申请全额退款
        }

        //todo 问题：已经换过一次货的商品，是否需要过滤掉换货售后记录的退款数量？
        //订单已售后总数量，这里要不要过滤换货售后
        $refundedNum = RefundGoodsLog::where('order_id',$this->order->id)
            ->where('refund_type', '!=', RefundApply::REFUND_TYPE_EXCHANGE_GOODS)
            ->sum('refund_total');

        if ($orderGoodsNum == ($currentApplyNum + $refundedNum)) {
            return 2; //最后一次退款
        }

        return 1; //部分退款
    }

    //后端订单列表是否显示部分退款按钮
    public function orderListDisplayButton()
    {
        return $this->multipleRefund();
    }

    //订单所属类型
    abstract public function isBelongTo();

    /**
     * 订单售后次数限制
     * @return int|bool false不限制次数直到没有可退数量
     */
    abstract public function applyNumberLimit();

    /**
     * 是否支持部分退款
     * @return bool true 支持 false 不支持
     */
    abstract public function multipleRefund();


    /**
     * 订单是否可进行申请售后验证
     * @throws AppException
     */
    abstract public function applyBeforeValidate();

    /**
     * 前端售后申请提交验证
     * @throws AppException
     */
    public function applySubmitValidate()
    {

    }


    //本次可进行售后申请的商品数据处理
    public function canApplyRefundGoods()
    {
        //处理订单可退款商品数量
        $orderGoods = $this->order->orderGoods->map(function ($orderGoods) {
            $orderGoods->refundable_total = $orderGoods->total - $orderGoods->after_sales['refunded_total'];
            $orderGoods->unit_price = bankerRounding($orderGoods->payment_amount / $orderGoods->total);
            $goods_trade = GoodsTradeSet::where('goods_id', $orderGoods->goods_id)->first();
            if ($goods_trade && $goods_trade->hide_status) {
                $begin_hide_day = $goods_trade->begin_hide_day;
                if ($begin_hide_day > 1) {
                    $begin_hide_day -= 1;
                    $begin_time = $this->order->pay_time->addDays($begin_hide_day)->format('Y-m-d');
                } else {
                    $begin_time = $this->order->pay_time->format('Y-m-d');
                }
                $begin_time .= " {$goods_trade->begin_hide_time}:00";
                $begin_timestamp = strtotime($begin_time);
                $end_hide_day = $goods_trade->end_hide_day;
                if ($end_hide_day) {
                    $end_time = Carbon::createFromTimestamp($begin_timestamp)->addDays(1)->format('Y-m-d');
                } else {
                    $end_time = Carbon::createFromTimestamp($begin_timestamp)->format('Y-m-d');
                }
                $end_time .= " {$goods_trade->end_hide_time}:00";
                $end_timestamp = strtotime($end_time);
                if ($begin_timestamp < time() && $end_timestamp > time()) {
                    return false;
                }
            }
            return $orderGoods;
        })->filter()->values();

        return $orderGoods->toArray();
    }

    /**
     * 格式化订单数据
     * @return array
     */
    public function formatOrder()
    {
        return  $this->order->makeHidden(['orderGoods'])->toArray();
    }



    //订单的运费金额
    public function getOrderFreightPrice()
    {
        return $this->order->dispatch_price;
    }


    //订单其他费用退款
    public function getOrderOtherPrice()
    {
        return $this->order->fee_amount + $this->order->service_fee_amount;
    }

    public function getReturnAddress()
    {
        //待退货显示寄回地址
        if ($this->refundApply->refund_type != RefundApply::REFUND_TYPE_REFUND_MONEY
            && $this->refundApply->status == RefundApply::WAIT_RETURN_GOODS) {
            return $this->__returnGoodsAddress();

        }

        //仅退款不需要显示寄回地址
        //用户已发货不需要显示寄回地址
        return [];
    }

    public function __returnGoodsAddress()
    {

        //售后有指定寄回地址
        if ($this->refundApply->refund_address) {
            $address = ReturnAddress::where('id', $this->refundApply->refund_address)->first();

            if ($address) {
                $address->refund_order_goods = $this->refundApply->refundOrderGoods;
                return [$address->toArray()];
            }
        }

        //默认获取默认地址
        $plugins_id = 0;
        if( $this->order->plugin_id == 92) {
            $plugins_id = 1;
        } elseif ($this->order->plugin_id == 32) {
            $plugins_id = 32;
        }

        $address = ReturnAddress::getOneByPluginsId($plugins_id, $this->refundApply->store_id?:0, $this->refundApply->supplier_id?:0);

        if (app('plugins')->isEnabled('area-dividend')) {

            $agentOrder = \Yunshop\AreaDividend\models\AgentOrder::select()
                ->where('order_id', $this->refundApply->order_id)
                ->first();
            if ($agentOrder) {
                $address = \app\common\models\goods\ReturnAddress::uniacid()
                    ->where('plugins_id', \app\common\modules\shop\ShopConfig::current()->get('plugins.area-dividend.id'))
                    ->where('store_id', $agentOrder->agent_id)
                    ->where('is_default', 1)
                    ->first();
            }
        }

        if ($address) {
            $address->refund_order_goods = $this->refundApply->refundOrderGoods;
            return [$address->toArray()];
        }

        return [];
    }


    /**
     * 订单已退款完成记录
     * @return \app\framework\Database\Eloquent\Collection
     */
    public function getAfterSales()
    {
        if (!isset($this->refundedCollect)) {
             $this->refundedCollect = \app\common\models\refund\RefundApply::getAfterSales($this->order->id)->get();
        }

        return  $this->refundedCollect;
    }
}