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
use app\common\payment\setting\integration\HfMiniIntegrationSetting;
use app\common\services\payment\IntegrationPay;

class HfMiniIntegrationPayment extends BasePayment
{
    public $code = 'hfMiniIntegrationPay';

    protected $scenes = ['shopOrder','storeOrder','cashierOrder'];

    public function __construct(HfMiniIntegrationSetting $paymentSetting)
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
            'pay_type' => 'MINIAPP_PAY',
            'spbill_create_ip'=>  $_SERVER['SERVER_ADDR'],
            'attach' => \YunShop::app()->uniacid,
            'pass_back' => [],
            'settle_type' => 0,
            'pay_info' => [],
            'notify_url' =>  Url::shopSchemeUrl('payment/hfMiniIntegrationPay/notify.php'),
        ];


        $result = IntegrationPay::pay($payParam);

        //请求数据日志
        $this->payRequestDataLog($data['order_no'], 142, '汇付小程序-聚合支付', $payParam);


        return $this->success(['mode' => 2,
            'appid' => 'wx11361ccf7f47b948',
            'path' => $result['fields']['counter_url'],
        ]);

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

            $this->payResponseDataLog($result['pay_data']['out_trade_no'],'汇付小程序-聚合支付', $data);

            return $result;

        } catch (\Exception $e) {
            \Log::debug("--{$this->code}-服务商系统聚合支付回调失败---".$e->getMessage(),[request()->all()]);
            return ['response' => $e->getMessage()];
        }
    }
}