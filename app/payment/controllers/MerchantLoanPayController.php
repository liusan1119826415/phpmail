<?php

namespace app\payment\controllers;

use app\common\models\PayOrder;
use app\common\models\PayType;
use app\payment\PaymentController;
use Yunshop\MerchantLoanPay\services\MerchantLoanService;

class MerchantLoanPayController extends PaymentController
{
    public function notifyUrl(): string
    {
        $request = request()->all();
        $processOrder = $this->processOrder($request);
        if ($processOrder) {
            return 'success';
        } else {
            return 'fail';
        }
    }

    /**
     * @param $data
     * @return bool
     */
    protected function processOrder($data): bool
    {
        $payOrder = PayOrder::where('out_order_no', $data['data']['thirdOrderNo'])->first();

        if (!$payOrder) {
            $this->debugMsg('未找到支付订单', $data);
            return false;
        }

        \YunShop::app()->uniacid = \Setting::$uniqueAccountId = $payOrder->uniacid;

        // 请求商户贷接口, 查看订单是否支付成功
        $service = new MerchantLoanService;

        $data = [
            'thirdOrderNo' => $data['data']['thirdOrderNo']
        ];

        $result = $service->userLoanQuery($data);

        if ($result['code'] === '0' && $result['data']['status'] == '10') {
            $currentPayType = $this->currentPayType($payOrder->type);
            $setData = $this->setData(
                $payOrder['out_order_no'],
                $payOrder['out_order_no'],
                bcdiv($result['data']['orderAmount'], 1000000, 2),
                $currentPayType['id'],
                $currentPayType['name']
            );
            $res = $this->payResutl($setData);

            if ($res) {
                $this->debugMsg('订单支付成功--订单号', $payOrder['out_trade_no']);
                return true;
            }
        }

        $this->debugMsg('订单支付失败--订单号', $payOrder['out_trade_no']);
        return false;
    }

    protected function debugMsg($title, $data = [])
    {
        \Log::debug(self::class . ' ' . $title . ': ' . json_encode($data));
    }

    protected function currentPayType($payId): \app\common\models\BaseModel
    {
        return PayType::find($payId);
    }

    /**
     * 支付回调参数
     *
     * @param $order_no
     * @param $trade_no
     * @param $total_fee
     * @param $pay_type_id
     * @param $pay_type
     * @return array
     */
    public function setData($order_no, $trade_no, $total_fee, $pay_type_id, $pay_type): array
    {
        return [
            'total_fee' => $total_fee,
            'out_trade_no' => $order_no,
            'trade_no' => $trade_no,
            'unit' => 'yuan',
            'pay_type' => $pay_type,
            'pay_type_id' => $pay_type_id,
        ];
    }
}
