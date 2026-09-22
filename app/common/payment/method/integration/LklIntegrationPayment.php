<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/1/4
 * Time: 14:43
 */

namespace app\common\payment\method\integration;

use app\common\helpers\Url;
use app\common\payment\method\BasePayment;
use app\common\payment\setting\integration\LklIntegrationSetting;
use app\common\services\payment\IntegrationPay;

class LklIntegrationPayment extends BasePayment
{
    public $code = 'lklIntegrationPay';

    protected $scenes = ['shopOrder','storeOrder','cashierOrder'];

    public function __construct(LklIntegrationSetting $paymentSetting)
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
            'pay_type' => 'CASH',
            'spbill_create_ip'=>  $_SERVER['SERVER_ADDR'],
            'pass_back' => [],
            'attach' => \YunShop::app()->uniacid,
            'settle_type' => 0,
            'pay_info' => [],
            'notify_url' =>  Url::shopSchemeUrl('payment/lklIntegrationPay/notify.php'),
        ];


        $result = IntegrationPay::pay($payParam);

        //请求数据日志
        $this->payRequestDataLog($data['order_no'], 134, '拉卡拉-收银台', $payParam);


        if (request()->input('type') == 2) {
            return $this->success(['mode' => 2,
                'appid'=> 'wxc3e4d1682da3053c',
                'path' =>  'payment-cashier/pages/checkout/index?source=WECHATMINI&counterUrl='.urlencode($result['fields']['counter_url']),
            ]);
        }

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

            $this->payResponseDataLog($result['pay_data']['out_trade_no'],'拉卡拉-收银台', $data);

            return $result;

        } catch (\Exception $e) {
            \Log::debug("--{$this->code}-服务商系统聚合支付回调失败---".$e->getMessage(),[request()->all()]);
            return ['response' => $e->getMessage()];
        }
    }
}