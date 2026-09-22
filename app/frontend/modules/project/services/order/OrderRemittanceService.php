<?php

namespace app\frontend\modules\project\services\order;

use app\common\models\project\OrderStage;
use app\frontend\models\OrderPay;
use app\common\models\Order;
use app\common\modules\payType\remittance\models\flows\RemittanceAuditFlow;
use app\common\modules\payType\remittance\models\process\RemittanceAuditProcess;
use Yunshop\Supplier\common\models\SupplierInstallPrice;
use Yunshop\Supplier\common\models\SupplierLogisticPrice;

/**
 * 汇款支付结果服务
 * 负责：获取汇款支付结果、支付明细
 */
class OrderRemittanceService
{
    /**
     * 获取汇款支付结果
     */
    public function getRemittanceResult($order_pay_id): array
    {
        $orderPay = OrderPay::find($order_pay_id);
        $order_first = $orderPay->orders->first();

        $data = [];
        if ($orderPay->pay_type_id == 16) {
            $data = [
                "remittance_data" => [
                    ['title' => '收款公司', 'text' => \Setting::get('shop.pay.remittance_bank_account_name')],
                    ['title' => '银行账号', 'text' => \Setting::get('shop.pay.remittance_bank_account')],
                    ['title' => '开户行', 'text' => \Setting::get('shop.pay.remittance_bank')],
                    ['title' => '行号', 'text' => \Setting::get('shop.pay.remittance_sub_bank')],
                ]
            ];

            $remittanceAuditFlow = RemittanceAuditFlow::first();
            $processBuilder = RemittanceAuditProcess::where('flow_id', $remittanceAuditFlow->id)
                ->whereHas('remittanceRecord', function ($query) use ($order_pay_id) {
                    $query->where('order_pay_id', $order_pay_id);
                })->first();

            if ($processBuilder->state == "processing") {
                $process_status = 0;
            } elseif ($processBuilder->state == "completed") {
                $process_status = 1;
                $data['pay_finish_time'] = $processBuilder->updated_at->format('Y.m.d H:i:s');
            } else {
                $process_status = 2;
            }

            $data['status'] = $process_status;
            $data['status_name'] = $processBuilder->status_name;
            $data['pay_time'] = $processBuilder->created_at->format('Y.m.d H:i:s');
        } else {
            $data['status'] = $orderPay->status;
            $data['status_name'] = $orderPay->status == 1 ? "支付成功" : "等待支付结果";
            $data['pay_time'] = $orderPay->pay_time ? $orderPay->pay_time->format('Y.m.d H:i:s') : "";
        }

        $data['pay_type_name'] = $orderPay->pay_type_name;
        $data['order_sn'] = $order_first->order_sn;
        $data['pay_type'] = $orderPay->pay_type_id;

        $payAmount = $this->formatNumber($orderPay->amount);
        $total_amount = 0;
        $freight_price = 0;
        $install_price = 0;

        if ($order_first->order_type == 1) {
            if ($orderPay->payment_stage == 2) {
                $supplier_logistic_lock = SupplierLogisticPrice::where('order_id', $order_first->id)->where('lock_status', 1)->first();
                $supplier_install_lock = SupplierInstallPrice::where('order_id', $order_first->id)->where('lock_status', 1)->first();
                $freight_price = $supplier_logistic_lock ? $supplier_logistic_lock->freight_price : 0;
                $install_price = $supplier_install_lock ? $supplier_install_lock->freight_price : 0;
            }

            $payAmount = $payAmount - ($freight_price + $install_price);
            $data['pay_data'][] = [
                "name" => $orderPay->payment_stage == 1 ? "项目预付款" : "出货结算款",
                "amount" => $this->formatNumber($payAmount),
            ];

            if ($orderPay->payment_stage == 1) {
                $orderStagePayment = OrderStage::where('order_id', $order_first->id)->where('stage', 2)->first();
                $data['pay_data'][] = [
                    "name" => "剩余尾款",
                    "amount" => $this->formatNumber($order_first->goods_price - $payAmount),
                    "status" => $orderStagePayment->status == 1 ? "已支付" : "待支付",
                    'status_code' => $orderStagePayment->status
                ];
            }

            $total_amount += $payAmount;

            if ($orderPay->payment_stage == 2) {
                if ($order_first->choose_logistics == 1) {
                    $data['pay_data'][] = ["name" => "物流运输费", "amount" => $freight_price];
                }
                if ($order_first->choose_install == 1) {
                    $data['pay_data'][] = ["name" => "安装搬运费", "amount" => $install_price];
                }
                $logistic_price = $order_first->choose_logistics == 1 ? $freight_price : 0;
                $install_price = $order_first->choose_install == 1 ? $install_price : 0;
                $total_amount += $this->formatNumber($logistic_price + $install_price);
            }

            $data['pay_data'][] = ["name" => "支付总额", "amount" => $total_amount];
            $data['total_pay_amount'] = $total_amount;

            // 获取生产周期
            $orderMainGoods = $order_first->orderMainGoods;
            $lead_time = 0;
            foreach ($orderMainGoods as $orderGoods) {
                $lead_time = max($lead_time, $orderGoods->hasOneGoods->lead_time);
            }

            $pay_time_timestamp = ($order_first->pay_time == "1970-01-01 08:00:00" || empty($order_first->pay_time))
                ? 0
                : strtotime($order_first->pay_time);

            $production_completion_time = $pay_time_timestamp + ($lead_time * 86400);
            $remaining_days = ceil(($production_completion_time - time()) / 86400);
            $remaining_days = max($remaining_days, 0);

            $data['production_completion_time'] = $production_completion_time > 0 ? date('Y-m-d', $production_completion_time) : '';
            $data['remaining_days'] = $remaining_days;
        } else {
            $data['pay_data'] = [
                ["name" => "支付总额", "amount" => $this->formatNumber($order_first->price)],
            ];
            $data['total_pay_amount'] = $this->formatNumber($order_first->price);
        }

        $data['order_type'] = $order_first->order_type;
        return $data;
    }

    /**
     * 格式化数字
     */
    protected function formatNumber($number)
    {
        return round((float)$number, 2);
    }
}
