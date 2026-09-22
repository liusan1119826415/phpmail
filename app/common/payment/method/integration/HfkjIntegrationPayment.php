<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/5/27
 * Time: 15:52
 */

namespace app\common\payment\method\integration;


use app\common\helpers\Url;
use app\common\payment\method\BasePayment;
use app\common\payment\setting\integration\HfkjIntegrationSetting;
use app\common\services\payment\IntegrationPay;

class HfkjIntegrationPayment  extends BasePayment
{

    public $code = 'hfkjIntegrationPay';

    protected $scenes = ['shopOrder', 'storeOrder', 'cashierOrder'];

    public function __construct(HfkjIntegrationSetting $paymentSetting)
    {
        $this->setSetting($paymentSetting);
    }

    public function isMobile() {

        if (request()->input('type') == 5) {
            $userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);
            $mobileAgents = ["android", "blackberry", "iphone", "ipad", "ipod", "iemobile", "opera mini", "webos", "googlebot-mobile"];
            $isMobile = false;
            foreach ($mobileAgents as $agent) {
                if (strpos($userAgent, strtolower($agent)) !== false) {
                    $isMobile = true;
                    break;
                }
            }

            return $isMobile;
        }

        return true;
    }

    public function doPay($data)
    {

        $order_pay_id = request()->input('order_pay_id');

        $paySet = \Setting::get('payment.integration_pay');

        $payParam = [
            'out_trade_no' => $data['order_no'],
            'total_amount' => bcmul($data['amount'], 100, 0),
            'subject' => $data['subject'],
            'payment_store_code' => $paySet['account_info']['merchant_no'],
            'pay_type' => 'QUICKPAY',
            'spbill_create_ip' => $_SERVER['SERVER_ADDR'],
            'attach' => \YunShop::app()->uniacid,
            'settle_type' => 0,
            'pass_back' => [],
            'pay_info' => [
                'member_id' => \YunShop::app()->getMemberId(),
            ],
            'request_type' => $this->isMobile() ? 'M':'P',
            'notify_url' => Url::shopSchemeUrl('payment/'.$this->code.'/notify.php'),
            'front_url' =>  Url::absoluteApp('order/pay_back/'.$order_pay_id.'/'.$data['order_no']),
        ];


        $result = IntegrationPay::pay($payParam);

        //请求数据日志
        $this->payRequestDataLog($data['order_no'], 141, '汇付快捷-聚合支付', $payParam);


        return $this->success(['mode' => 1, 'url' => $result['fields']['counter_url']]);
    }

    public function doRefund($data)
    {
        $result = IntegrationPay::refund($data);

        return true;
    }

    public function doWithdraw()
    {
        // TODO: Implement doWithdraw() method.
    }

    public function callback()
    {
        try {
            $data = request()->all();

            $result = IntegrationPay::payNotice($data);

            $this->payResponseDataLog($result['pay_data']['out_trade_no'], '汇付快捷-聚合支付', $data);

            return $result;

        } catch (\Exception $e) {
            \Log::debug("--{$this->code}-服务商系统聚合支付回调失败---" . $e->getMessage(), [request()->all()]);
            return ['response' => $e->getMessage()];
        }
    }
}