<?php
/**
 * Created by PhpStorm.
 * User: blank
 * Date: 2022/11/4
 * Time: 10:22
 */

namespace app\common\modules\refund;


use app\common\models\PayType;
use app\common\modules\refund\services\RefundService;
use app\common\payment\method\BasePayment;
use app\common\payment\PaymentManager;
use app\common\services\Pay;
use app\common\services\PayFactory;

/**
 * 统一化调用退款方法返回的数据格式
 * Class RefundPayAdapter
 * @package app\common\modules\refund
 */
class RefundPayAdapter
{

    protected $pay;

    //protected $pay_sn;
    //protected $refund_sn;
    //protected $total_amount;
    //protected $refund_amount;

    protected $attributes = [];

    public function __construct($pay_type_id)
    {
        $this->setAttribute('pay_type_id', $pay_type_id);
    }

    public function __get($key)
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->getAttribute($key);
        }

        return $this->$key;
    }

    public function setAttribute($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    public function getAttribute($key, $default = '')
    {
        if (!isset($this->attributes[$key]) || $this->attributes[$key] === '') {
            return $default;
        }

        return $this->attributes[$key];
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @return Pay
     */
    public function payClass()
    {
        if (!isset($this->pay)) {
            $this->pay = PayFactory::create($this->getAttribute('pay_type_id'));
        }

        return $this->pay;
    }

    protected function getPayTypeRefund()
    {
        $result = $this->payClass()->doRefund($this->pay_sn, $this->total_amount, $this->refund_amount, $this->getAttribute('refund_sn'));

        return $result;
    }

    /**
     * @param string $pay_sn
     * @param double $total_amount
     * @param double $refund_amount
     * @param string $request_no
     * @return array|bool|mixed|string
     */
    public function pay($pay_sn, $total_amount, $refund_amount, $refund_sn = '')
    {

        $this->setAttribute('pay_sn', $pay_sn);

        $this->setAttribute('total_amount', $total_amount);

        $this->setAttribute('refund_amount', $refund_amount);

        $this->setAttribute('refund_sn', $refund_sn);

        //$this->pay_sn = $pay_sn;
        //$this->total_amount = $total_amount;
        //$this->refund_amount = $refund_amount;
        //$this->refund_sn = $request_no;

        try {
            $result = $this->refundPay();

            if (!$result['status']) {
                \Log::debug('<---doRefund退款请求失败------'.$this->pay_sn, $result);
            }

            return $result;
        } catch (\Exception $exception) {

            \Log::debug('<---doRefund退款失败------'.$this->pay_sn, $exception->getMessage());

            return $this->fail(['pay_sn' => $pay_sn], $exception->getMessage());
        }

    }


    public function refundPay()
    {
        switch ($this->getAttribute('pay_type_id')) {
            case PayType::WECHAT_PAY:
            case PayType::WECHAT_MIN_PAY:
            case PayType::WECHAT_H5:
            case PayType::WECHAT_NATIVE:
            case PayType::WECHAT_JSAPI_PAY:
            case PayType::WechatApp:
            case PayType::WECHAT_CPS_APP_PAY:
                $result = $this->wechat();
                break;
            case PayType::ALIPAY:
            case PayType::AlipayApp:
                $result = $this->alipay();
                break;
            case PayType::CREDIT:
                $result = $this->balance();
                break;
            case PayType::WECHAT_HJ_PAY:
            case PayType::ALIPAY_HJ_PAY:
            case PayType::CONVERGE_UNION_PAY:
                $result = $this->convergePayRefund();
                break;
            case PayType::CONVERGE_QUICK_PAY:
                $result = $this->convergeQuickPay();
                break;
            case PayType::AUTH_PAY:
                $result = $this->authPayRefund();
                break;
            default:
                $result = $this->noAdapterType();
        }

        return $result;
    }


    //微信JSAPI、H5、NATIVE、小程序、APP支付退款入口
    protected function wechat()
    {

        $result = $this->getPayTypeRefund();

        if (!$result) {
            return $this->fail($result, '微信支付类退款方法失败');
        }

        return $this->success($result);
    }

    protected function alipay()
    {

        $result = $this->getPayTypeRefund();

        if ($result === false) {
            return $this->fail($result, '支付宝类退款方法失败');
        }

        return $this->success($result);
    }

    protected function balance()
    {
        $result = $this->getPayTypeRefund();

        if ($result !== true) {
            return $this->fail($result, '余额退款方法失败');
        }
        return $this->success($result);
    }

    protected function convergePay()
    {

        $result = $this->getPayTypeRefund();

        if ($result['ra_Status'] == '101') {
            return $this->fail($result, '汇聚微信或支付宝退款失败，失败原因' . $result['rc_CodeMsg']);
        }

        return $this->success($result);
    }

    /**
     * 汇聚聚合支付统一退款方法
     * @return array|bool|mixed|string
     */
    protected function convergePayRefund()
    {

        $result = $this->getPayTypeRefund();

        if ($result['ra_Status'] == '101') {
            return $this->fail($result, '汇聚退款失败，失败原因:' . $result['rc_CodeMsg']);
        }

        return $this->success($result);

    }

    protected function convergeQuickPay()
    {
        $result = $this->getPayTypeRefund();


        if (!$result['code']) {
            return $this->fail($result, $result['msg']);
        }

        return $this->success($result);

    }

    protected function authPayRefund()
    {
        $result = $this->getPayTypeRefund();

        if (!$result) {
            return $this->fail($result, '微信借权支付退款失败');
        }

        if (is_array($result) && isset($result['msg']) && $result['msg'] == '失败') {
            return $this->fail($result, $result['pay_msg'] ? : '微信借权支付退款失败');
        }

        return $this->success($result);
    }

    protected function noAdapterType()
    {
        //增加新支付方式
        if ($this->pay_type_id >= 121) {
            $pay_code = PayType::where('id', $this->pay_type_id)->value('code');
            $data = [
                'pay_sn' => $this->pay_sn,
                'total_amount' => $this->total_amount,
                'refund_amount' => $this->refund_amount,
                'refund_sn' => $this->getAttribute('refund_sn',\app\frontend\modules\refund\services\RefundService::createOrderRN()),
            ];
            $result = app(PaymentManager::class)->get($pay_code)->refundPayment($data);
        } else {
            $result = $this->getPayTypeRefund();
        }

        if (!$result) {
            \Log::debug("<--{$this->pay_type_id}----没适配器支付方式退款-------".$this->pay_sn, $result);
            return $this->fail($result);
        }


        return $this->success($result);
    }

    public function fail($result,$msg = '')
    {
        if (!$msg) {
            $msg = $this->getAttribute('pay_name').'退款请求失败';
        }

        return $this->format(0, $msg, $result);
    }

    public function success($result,$msg = '')
    {
        if (!$msg) {
            $msg = $this->getAttribute('pay_name').'退款请求成功';
        }

        return $this->format(1, $msg, $result);
    }

    protected function format($status, $msg = '', $result)
    {
        return ['status' => $status, 'msg' => $msg, 'data' => $result];
    }
}