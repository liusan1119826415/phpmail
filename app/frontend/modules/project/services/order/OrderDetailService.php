<?php

namespace app\frontend\modules\project\services\order;

use app\common\models\Order;
use app\frontend\models\OrderGoods;
use app\frontend\models\OrderPay;
use app\frontend\modules\project\models\Project;
use app\common\modules\payType\remittance\models\flows\RemittanceAuditFlow;
use app\common\modules\payType\remittance\models\process\RemittanceAuditProcess;
use app\common\models\project\LogisticsReminder;
use Yunshop\Supplier\common\models\SupplierInstallPrice;
use Yunshop\Supplier\common\models\SupplierLogisticPrice;

class OrderDetailService
{
    /**
     * 订单详情主方法
     * @param int $order_id
     * @return array
     */
    public static function detail(int $order_id): array
    {
        $order = self::getOrderWithRelations($order_id);
        if (!$order) {
            return [];
        }

        self::loadOrderExtensions($order);
        self::formatOrderPayments($order);
        self::mergeFakeData($order);
        self::calculateAmounts($order);

        return $order->toArray();
    }

    /**
     * 获取订单及其关联数据
     */
    public static function getOrderWithRelations(int $orderId)
    {
        return app('OrderManager')->make('Order')
            ->where('id', $orderId)
            ->with([
                'myOrderAddress' => function ($q) {
                    $q->select("id", "order_main_id", "realname", "address", "mobile");
                },
                'hasOneOrderPay',
                'orderPayments',
                'hasOneRefundApply' => function ($q) {
                    $q->select("id", "refund_sn", "price", "status", "apply_price", "reason", "content", "refund_time", "created_at");
                }
            ])
            ->first();
    }

    /**
     * 加载订单的附加信息：项目名称、待确认图纸数、子订单数、物流安装锁定供应商等
     */
    public static function loadOrderExtensions($order)
    {
        $order->project_name = Project::where('id', $order->project_id)->value('name');
        $order->confirm_num = OrderGoods::where('order_main_id', $order->id)
            ->where('confirm_status', 1)
            ->where('upload_time', '>', 0)
            ->count();
        $order->point_count = Order::where('parent_id', $order->id)->count();

        // 物流与安装的锁定供应商（用于后续展示）
        $order->supplier_logistic_lock = SupplierLogisticPrice::with('supplier')
            ->where('order_id', $order->id)
            ->where('lock_status', 1)
            ->first();
        $order->supplier_install_lock = SupplierInstallPrice::with('supplier')
            ->where('order_id', $order->id)
            ->where('lock_status', 1)
            ->first();
    }

    /**
     * 处理订单支付记录（添加 process_status 字段）
     */
    public static function formatOrderPayments($order)
    {
        $order->orderPayments = $order->orderPayments->map(function ($payment) {
            $payment->process_status = self::getPaymentProcessStatus($payment->pay_id);
            return $payment;
        });
    }

    public static function getPaymentProcessStatus($payId): int
    {
        if (!$payId) {
            return -3;
        }

        $flow = RemittanceAuditFlow::first();
        if (!$flow) {
            return -3;
        }

        $process = RemittanceAuditProcess::where('flow_id', $flow->id)
            ->whereHas('remittanceRecord', function ($query) use ($payId) {
                $query->where('order_pay_id', $payId);
            })
            ->first();

        if (!$process) {
            return -3;
        }

        // 兼容 PHP < 8.0，不使用 match
        if ($process->state == 'processing') {
            return 0;
        } elseif ($process->state == 'completed') {
            return 1;
        } else {
            return 2;
        }
    }

    /**
     * 整合支付阶段与物流安装等假数据，生成前端需要的 orderPayments 数组
     */
    public static function mergeFakeData($order)
    {
        // 1. 构建真实支付阶段的数据
        $realPayments = [];
        $advancePayment = $tailPayment = 0;
        $payableAmount = 0;
        $goodsPayAmount = 0;

        foreach ($order->orderPayments as $payment) {
            $stage = $payment->stage;
            $price = $payment->price;
            $status = $payment->status;

            if ($status == 1) {
                $goodsPayAmount += $price;
            }

            if ($stage == 1) {
                $advancePayment = $price;
            } elseif ($stage == 2) {
                $tailPayment = $price;
            }

            if ($stage == $order->payment_stage) {
                $payableAmount = $price;
            }

            $statusName = self::getPaymentStatusName($order->status, $status);
            $payId = $payment->pay_id;

            $realPayments[] = [
                "name"        => $stage == 1 ? "项目预付款 (启动生产)" : "出货结算款 (货物发出前结清)",
                "goods_num"   => "",
                "price"       => self::formatNumber($price),
                "type"        => $stage == 1 ? 2 : 3,
                "order_sn"    => ($status == 1 && $payId) ? OrderPay::where('id', $payId)->value('pay_sn') : "",
                "status"      => $status,
                "pay_amount"  => self::formatNumber($status == 1 ? $price : 0),
                "discount"    => [],
                "status_name" => $statusName,
                "button"      => $status == 1 ? "申请售后" : "",
                "order_pay_id" => $payId,
                "pay_type_id"  => $payment->pay_type_id,
            ];
        }

        // 2. 商品明细假数据
        $goodsFake = [
            "name"        => "商品明细",
            "goods_num"   => $order->goods_total,
            "price"       => self::formatNumber($order->goods_price),
            "pay_amount"  => self::formatNumber($goodsPayAmount),
            "order_sn"    => $order->order_sn,
            "type"        => 1,
            "status"      => $order->new_status,
            "discount"    => [],
            "status_name" => $order->status_name,
            "button"      => "商品清单"
        ];

        $finalPayments = array_merge([$goodsFake], $realPayments);

        // 3. 如果订单已完成到可查看物流安装的阶段，追加物流与安装信息
        if ($order->status >= 1 && $order->orderStatus >= 2) {
            $logisticData = self::buildLogisticFakeData($order);
            if ($logisticData) {
                $finalPayments[] = $logisticData;
            }
            $installData = self::buildInstallFakeData($order);
            if ($installData) {
                $finalPayments[] = $installData;
            }
        }

        // 存储计算结果以备后用
        $order->advance_payment = self::formatNumber($advancePayment);
        $order->tail_payment    = self::formatNumber($tailPayment);
        $order->payable_amount  = self::formatNumber($payableAmount);
        $order->goodsPayAmount  = $goodsPayAmount;

        $order->orderPayments = $finalPayments;
    }

    /**
     * 获取支付记录的状态名称
     */
    private static function getPaymentStatusName(int $orderStatus, int $paymentStatus): string
    {
        if ($orderStatus == -1) {
            return "已关闭";
        }
        return $paymentStatus == 0 ? "未支付" : "已支付";
    }

    /**
     * 构造物流假数据（包含物流跟踪状态）
     */
    private static function buildLogisticFakeData($order): ?array
    {
        $supplier = $order->supplier_logistic_lock;
        if (!$supplier) {
            return null;
        }

        $logisticTrack = $order->logistics;
        $status = $logisticTrack->current_status ?? 0;
        $statusName = $logisticTrack->status_name ?? '';

        $msgCount = LogisticsReminder::where('order_id', $order->id)
            ->where('user_id', $order->uid)
            ->where('is_read', 0)
            ->where('reminder_type', 1)
            ->count();

        return [
            "name"          => "物流运输",
            "logistic_unit" => $supplier->store_name ?? "",
            "phone"         => $supplier->supplier->mobile ?? "",
            "price"         => self::formatNumber($supplier->freight_price ?? 0),
            "pay_amount"    => self::formatNumber(($order->status >= 1 && $order->orderStatus == 3 && $order->choose_logistics == 1) ? ($supplier->freight_price ?? 0) : 0),
            "order_sn"      => "",
            "type"          => 4,
            "status"        => $status,
            "wait_status"   => 1,
            "discount"      => [],
            "status_name"   => $statusName,
            "msg_count"     => $msgCount,
            "button"        => "物流信息"
        ];
    }

    /**
     * 构造安装假数据
     */
    private static function buildInstallFakeData($order): ?array
    {
        $supplier = $order->supplier_install_lock;
        if (!$supplier) {
            return null;
        }

        $installTrack = $order->installTracks;
        $msgCount = LogisticsReminder::where('order_id', $order->id)
            ->where('user_id', $order->uid)
            ->where('is_read', 0)
            ->where('reminder_type', 2)
            ->count();

        return [
            "name"          => "安装搬运",
            "logistic_unit" => $supplier->store_name ?? "",
            "phone"         => $supplier->supplier->mobile ?? "",
            "price"         => self::formatNumber($supplier->freight_price ?? 0),
            "pay_amount"    => self::formatNumber(($order->status >= 1 && $order->orderStatus == 3 && $order->choose_install == 1) ? ($supplier->freight_price ?? 0) : 0),
            "order_sn"      => "",
            "type"          => 5,
            "status"        => $installTrack->current_status ?? 0,
            "wait_status"   => 1,
            "discount"      => [],
            "msg_count"     => $msgCount,
            "status_name"   => $installTrack->status_name ?? "",
            "button"        => "安装信息"
        ];
    }

    /**
     * 计算订单各金额汇总
     */
    public static function calculateAmounts($order)
    {
        // 已付款金额 = 商品已支付金额(goodsPayAmount = advance_payment + tail_payment) + 物流费(条件满足) + 安装费(条件满足)
        // goodsPayAmount 已在 mergeFakeData 中累加完成，此处直接复用，避免与 advance_payment/tail_payment 重复累加
        $paidFromPayments = $order->goodsPayAmount ?? 0;

        $logisticFee = 0;
        if ($order->status >= 1 && $order->orderStatus == 3 && $order->choose_logistics == 1 && $order->supplier_logistic_lock) {
            $logisticFee = $order->supplier_logistic_lock->freight_price ?? 0;
        }

        $installFee = 0;
        if ($order->status >= 1 && $order->orderStatus == 3 && $order->choose_install == 1 && $order->supplier_install_lock) {
            $installFee = $order->supplier_install_lock->freight_price ?? 0;
        }

        $totalPaid = $paidFromPayments + $logisticFee + $installFee;

        $order->pay_amount = self::formatNumber($totalPaid);
        $order->logictis_fee = self::formatNumber($order->freight_price ?? 0);
        $order->install_fee = self::formatNumber($order->install_price ?? 0);
        $order->goods_price = self::formatNumber($order->goods_price);

        // 额外处理支付超时倒计时
        if ($order->status == 0 && $order->confirm_status == 1 && $order->payment_stage == 1 && $order->orderStatus == 0) {
            $order->close_time = max(0, $order->pay_expire_time - time());
        }

        // 处理汇款支付状态
        if ($order->pay_type_id == 16 && $order->order_pay_id) {
            $order->process_status = self::getPaymentProcessStatus($order->order_pay_id);
        }
    }

    /**
     * 格式化数字保留两位小数
     */
    private static function formatNumber($value): string
    {
        return number_format((float)$value, 2, '.', '');
    }
}
