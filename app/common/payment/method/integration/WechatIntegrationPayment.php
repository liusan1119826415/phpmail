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

use app\common\exceptions\AppException;
use app\common\helpers\Url;
use app\common\models\Member;
use app\common\models\OrderRefund;
use app\common\payment\method\BasePayment;
use app\common\payment\setting\integration\WechatIntegrationSetting;
use app\common\services\payment\IntegrationPay;

class WechatIntegrationPayment extends BasePayment
{
    public $code = 'wxIntegrationPay';

    protected $scenes = ['shopOrder','storeOrder','cashierOrder'];

    public function __construct(WechatIntegrationSetting $paymentSetting)
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
            'pay_type' => 'WECHAT',
            'spbill_create_ip'=> $_SERVER['SERVER_ADDR'],
            'attach' => \YunShop::app()->uniacid,
            'pass_back' => [],
            'settle_type' => 0,
            'pay_info' => [
                'open_id' => $this->getOpenId(\YunShop::app()->getMemberId()),
                'app_id' => $this->getAppId(),
            ],
            'notify_url' =>  Url::shopSchemeUrl('payment/wxIntegrationPay/notify.php'),
        ];

        $result = IntegrationPay::pay($payParam);

        //请求数据日志
        $this->payRequestDataLog($data['order_no'], 130, '微信-聚合支付', $payParam);

        $config = $result['fields'];

        return $this->success(['mode' => 3, 'config' =>[
            'prepayId'=> $config['prepay_id'],//预下单Id
            'paySign'=> $config['pay_sign'],//支付签名信息
            'appId'=> $config['app_id'],//小程序id
            'timeStamp'=> $config['time_stamp'],//小程序id
            'nonceStr'=> $config['nonce_str'],//随机字符串
            'package'=> $config['package'],//订单详情扩展字符串
            'signType'=> $config['sign_type'],//签名方式
        ]]);
    }

    /**
     * @param $member_id
     * @return mixed|string
     * @throws AppException
     */
    public function getOpenId($member_id)
    {
        $openid = \app\common\models\Member::getOpenIdForType($member_id, request()->input('type'));
        if (empty($openid)) {
            throw new AppException('用户openid不存在');
        }

        return $openid;

    }

    public function getAppId()
    {
        if (request()->input('type') == 2) {
            $minSet = \Setting::get('plugin.min_app');

            if (!$minSet['key']) {
                throw new AppException('小程序支付配置未设置');
            }

            return $minSet['key'];
        }

        //独立版
        if (config('APP_Framework') == 'platform') {
            $app_id = \Setting::get('plugin.wechat')['app_id'];
        } else {
            $account = \app\common\models\AccountWechats::getAccountByUniacid(\YunShop::app()->uniacid);
            $app_id = $account['key'];
        }

        if (!$app_id) {
            throw new AppException('商城未配置公众号');
        }


        return $app_id;
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
            $result =  IntegrationPay::payNotice($data);

            $this->payResponseDataLog($result['pay_data']['out_trade_no'],'微信-聚合支付', $data);

            return $result;

        } catch (\Exception $e) {
            \Log::debug("--{$this->code}-服务商系统聚合支付回调失败---".$e->getMessage(),[request()->all()]);
            return ['response' => $e->getMessage()];
        }
    }
}