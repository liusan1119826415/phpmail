<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/11/13
 * Time: 11:04
 */

namespace app\common\payment\method\integration;

use app\common\helpers\Url;
use app\common\payment\method\BasePayment;
use app\common\payment\setting\integration\AlipayIntegrationSetting;
use app\common\services\payment\IntegrationPay;

class AlipayIntegrationPayment extends BasePayment
{
    public $code = 'zfbIntegrationPay';

    protected $scenes = ['shopOrder','storeOrder','cashierOrder'];

    public function __construct(AlipayIntegrationSetting $paymentSetting)
    {
        $this->setSetting($paymentSetting);
    }

    public function doPay($data)
    {


        $paySet =  \Setting::get('payment.integration_pay');

        $payParam = [
            'out_trade_no' => $data['order_no'],
            'total_amount' => bcmul($data['amount'], 100,0),
            'subject' => $data['subject'],
            'payment_store_code' =>  $paySet['account_info']['merchant_no'],
            'pay_type' => 'ALIPAY',
            'spbill_create_ip'=>  $_SERVER['SERVER_ADDR'],
            'attach' => \YunShop::app()->uniacid,
            'pass_back' => [],
            'settle_type' => 0,
            'pay_info' => [],
            'notify_url' =>  Url::shopSchemeUrl('payment/zfbIntegrationPay/notify.php'),
        ];


        $result = IntegrationPay::pay($payParam);

        //请求数据日志
        $this->payRequestDataLog($data['order_no'], 127, '支付宝-聚合支付', $payParam);


        return $this->success(['mode' => 1, 'url' => $result['fields']['code']]);
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

            $this->payResponseDataLog($result['pay_data']['out_trade_no'],'支付宝-聚合支付', $data);

            return $result;

        } catch (\Exception $e) {
            \Log::debug("--{$this->code}-服务商系统聚合支付回调失败---".$e->getMessage(),[request()->all()]);
            return ['response' => $e->getMessage()];
        }
    }
}