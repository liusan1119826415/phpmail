<?php

namespace app\payment\controllers;

use app\common\facades\Setting;
use app\common\models\PayOrder;
use app\common\models\PayType;
use app\payment\PaymentController;
use Yunshop\IcbcPay\services\IcbcPayService;
use Yunshop\IcbcPay\services\IcbcSignature;
use Yunshop\IcbcPay\services\WebUtils;

class IcbcPayController extends PaymentController
{
    public function notifyUrl()
    {
        $this->debugMsg('工行数据', request()->all());

        $from = request()->input('from');
        $api = request()->input('api');
        $appId = request()->input('app_id');
        $charset = request()->input('charset');
        $format = request()->input('format');
        $timestamp = request()->input('timestamp');
        $sign_type = request()->input('sign_type');
        $biz_content = htmlspecialchars_decode(request()->input('biz_content'));
        $sign = request()->input('sign');
        $params['from'] = $from;
        $params['api'] = $api;
        $params['app_id'] = $appId;
        $params['charset'] = $charset;
        $params['format'] = $format;
        $params['timestamp'] = $timestamp;
        $params['sign_type'] = $sign_type;
        $params['biz_content'] = $biz_content;

        $path = $api;
        $signStr = WebUtils::buildOrderedSignStr($path, $params);
        $respMap = json_decode($biz_content, true);

        \YunShop::app()->uniacid = Setting::$uniqueAccountId = $respMap['attach'];
        $icbc = new IcbcPayService();
        $passed = IcbcSignature::verify($signStr, $icbc->getIcbcPublicKey(), $sign);

        if (!$passed) {

            $return_data = [
                'return_code' => -12345,
                'return_msg' => '工行验签失败',
            ];

            $responseBizContent = json_encode($return_data);
        } else {
            /**********合作方/分行 业务逻辑处理**********/
            $this->processOrder($respMap);
            $msg_id = $respMap["msg_id"];
            $return_data = [
                'return_code' => 0,
                'return_msg' => "success",
                $msg_id => $respMap["msg_id"]
            ];

            $responseBizContent = json_encode($return_data);
        }

        $signStr = "\"response_biz_content\":" . $responseBizContent . "," . "\"sign_type\":" . "\"RSA2\"";
        $sign = IcbcSignature::sign($signStr, $icbc->getPrivateKey(), 'RSA2');
        $results = "{" . $signStr . ",\"sign\":\"" . $sign . "\"}";
        echo $results;
    }

    /**
     * @param $data
     * @return bool
     */
    protected function processOrder($data): bool
    {
        $this->debugMsg('工行支付-修改订单状态前: ', $data);

        $payOrder = PayOrder::where('out_order_no', $data['out_trade_no'])->first();
        if (!$payOrder) {
            $this->debugMsg('未找到支付订单', $data);
            return false;
        }

        $currentPayType = $this->currentPayType($payOrder->type);
        $setData = $this->setData(
            $payOrder['out_trade_no'],
            $data['out_trade_no'],
            $data['total_amt'],
            $currentPayType['id'],
            $currentPayType['name']
        );
        $this->payResutl($setData);
        $this->debugMsg('订单支付成功--订单号', $data['out_trade_no']);

        return true;
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
            'unit' => 'fen',
            'pay_type' => $pay_type,
            'pay_type_id' => $pay_type_id,
        ];
    }
}
