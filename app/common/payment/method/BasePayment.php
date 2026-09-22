<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2021/8/26
 * Time: 14:01
 */

namespace app\common\payment\method;


use app\common\helpers\Url;
use app\common\models\OrderPay;
use app\common\models\PayAccessLog;
use app\common\models\PayLog;
use app\common\models\PayOrder;
use app\common\models\PayRequestDataLog;
use app\common\models\PayResponseDataLog;
use app\common\models\PayType;
use app\common\models\refund\PayRefundRecord;
use app\common\payment\PaymentManager;
use app\common\payment\setting\BasePaymentSetting;
use app\common\payment\types\BasePaymentTypes;
use app\common\services\Pay;
use Darabonba\GatewaySpi\Models\InterceptorContext\request;

abstract class BasePayment implements PaymentGatewayInterface
{
    public $BasePaymentTypes;
    public $BasePaymentSetting;
    public $pay_type;

    protected $doPayNotify;


    protected $scenes;


    public function canUse()
    {
        return $this->BasePaymentSetting->canUse() && $this->BasePaymentTypes->canUse($this->code);
    }

    public function canScene()
    {
        return $this->BasePaymentSetting->canScene($this->scenes);
    }

    public function setTypes(BasePaymentTypes $BasePaymentTypes): BasePayment
    {
        $this->BasePaymentTypes = $BasePaymentTypes;
        return $this;
    }

    public function getScene()
    {
        return $this->scenes;
    }


    protected function setSetting(BasePaymentSetting $BasePaymentSetting)
    {
        $this->BasePaymentSetting = $BasePaymentSetting;
        $this->BasePaymentSetting->payType = app(PaymentManager::class)->getPayType()->where('code', $this->code)->first() ?: new PayType();
    }

    public function __call($method, $parameters)
    {
        return $this->BasePaymentSetting->$method(...$parameters);
    }

    public function getOther()
    {
        return [];
    }

    public function doPay(array $data)
    {

    }


    public function refundPayment(array $data)
    {
        //todo $data 里根本没有 total_money 这个字段... 是叫 total_amount 坑人
        if (!isset($data['total_money'])) {
            $data['total_money'] = $data['total_amount'];
        }
        $op = $this->getName() . '退款 支付单号：' . $data['pay_sn'] . '退款单号：' . $data['refund_sn'] . '支付总金额：' . $data['total_money'] . '退款金额：' . $data['refund_amount'];
        $this->createPayLog(Pay::PAY_TYPE_REFUND, $data['refund_amount'], $op);
        //调用退款操作
        return $this->doRefund($data);
    }


    public function doWithdraw()
    {

    }


    public function doRefund(array $data)
    {

    }

    public function processPayment(array $data)
    {
        \Log::debug('--------pay-------$className',[static::class]);
        $text = $data['extra']['type'] == 1 ? '支付' : '充值';
        $op = $this->pay_type->name . '订单' . $text . ' 订单号：' . $data['order_no'];
        $this->createPayLog($data['extra']['type'], $data['amount'], $op);
        $this->doPayNotify = Url::shopSchemeUrl('payment/' . $this->code . '/notify.php');
        return $this->doPay($data);
    }

    public function callbackPayment()
    {
        $data = $this->callback();
        if (!empty($data['pay_data'])) {
            $data['pay_data']['pay_type'] = $this->getName();
            $data['pay_data']['pay_type_id'] = $this->getId();
        }
        return $data;
    }

    private function createPayLog($type, $amount, $op)
    {
        PayAccessLog::create([
            'uniacid' => \YunShop::app()->uniacid ?: 0,
            'member_id' => \YunShop::app()->getMemberId(),
            'url' => \URL::current() . '?' . $_SERVER['QUERY_STRING'],
            'http_method' => $_SERVER['REQUEST_METHOD'] ?: "CLI",
            'ip' => request()->getClientIp(),
            'input' => file_get_contents('php://input'),
        ]);
        PayLog::create([
            'uniacid' => \YunShop::app()->uniacid,
            'member_id' => \YunShop::app()->getMemberId(),
            'type' => $type,
            'third_type' => $this->getName(),
            'price' => $amount,
            'operation' => $op,
            'ip' => request()->getClientIp()
        ]);
    }

    /**
     * 支付请求数据记录
     * @param string $out_order_no  订单号
     * @param int $type  支付类型
     * @param string $third_type 支付方式
     * @param array $params 请求数据
     */
    protected function payRequestDataLog($out_order_no, $type, $third_type, $params)
    {

        PayRequestDataLog::create([
            'uniacid' => \YunShop::app()->uniacid,
            'out_order_no' => $out_order_no,
            'type' => $type,
            'third_type' => $third_type,
            'params' => json_encode($params),
        ]);
    }

    /**
     * 支付响应数据记录
     *
     * @param string $out_order_no  订单号
     * @param string $third_type 支付方式
     * @param array $params 响应结果
     */
    protected function payResponseDataLog($out_order_no, $third_type, $params)
    {
        PayResponseDataLog::create([
            'uniacid' => \YunShop::app()->uniacid ? : 0,
            'out_order_no' => $out_order_no,
            'third_type' => $third_type,
            'params' => json_encode($params),
        ]);
    }

    /**
     * 第三方退款记录，防止第三方退款失败，商城这边售后显示退款成功实际未退款问题
     * @param $pay_type_id
     * @param $pay_sn
     * @param $price
     * @param $refund_sn
     * @param int $status
     * @return mixed
     */
    protected function payRefundLog($pay_type_id,$pay_sn,$price,$refund_sn,$status = 0)
    {
        return PayRefundRecord::create([
            'uniacid' => \YunShop::app()->uniacid,
            'pay_type_id' => $pay_type_id,
            'amount' => $price,
            'pay_sn' => $pay_sn,
            'refund_sn' => $refund_sn,
            'status' => $status,
        ]);
    }

    /**
     * 一般都是不需要这里生成的，默认售后单号
     * 生成第三方退款请求号
     * @return string
     */
    public static function createPayRefundSn()
    {
        return PayRefundRecord::createRequestNo();
    }

    protected function success($data): array
    {
        return [
            'code' => 1,
            'data' => $data,
        ];
    }

    protected function fail($msg): array
    {
        return [
            'code' => 0,
            'msg' => $msg
        ];
    }

}