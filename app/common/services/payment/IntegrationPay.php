<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/11/13
 * Time: 14:01
 */

namespace app\common\services\payment;


use app\common\exceptions\AppException;
use app\common\services\Utils;
use Ixudra\Curl\Facades\Curl;
use Yunshop\IntegrationPayShare\models\ShareMerchant;

class IntegrationPay
{

    static public $channel_type_name = [
        0 => '未知',
        1 => '拉卡拉',
        2 => '汇付',
        3 => '乐刷',
    ];

    const CHANNEL_LKL = 1;
    const CHANNEL_HF = 2;

    /**
     * 服务商插件是否开启
     * @return int
     */
    static public function pluginSwitch()
    {
        if (app('plugins')->isEnabled('service-provider-connect')) {
            $set =  \Setting::get('plugin.service-provider-connect');

            return $set['is_open']?:0;
        }

        return 0;
    }


    /**
     * 支付配置
     * @return mixed
     */
    static public function getConfig()
    {

        $client = new \Yunshop\ServiceProviderConnect\common\ClientRequest();

        $client->setMethod('/api/open/plugins/payment/base');

        $result = $client->post();

        //参数处理
        if ($result['status']) {
            $account_info = $result['data'];

            if (!$account_info['channel']) {
                $account_info['channel'] = IntegrationPay::$channel_type_name[$account_info['channel_type']];
            }

            $account_info['merchant_no'] = $account_info['payment_store_code'];

            $result['data'] = $account_info;
        }

        return $result;
    }

    static public function getLklShareConfig()
    {
        
        $client = new \Yunshop\ServiceProviderConnect\common\ClientRequest();

        $client->setMethod('/api/open/plugins/payment/setting');

        $result = $client->post();

        return $result;
    }

    /**
     * @param $payParam
     * @return mixed|string
     * @throws \Exception
     */
    static public function pay($payParam)
    {

//       [
//            'merchant_no',//商户号
//            'out_trade_no', //支付号
//            'total_amount', //支付金额
//            'trade_type', //支付类型：微信、支付宝
//            'settle_type', //是否支持分账
//            'notify_url', //支付成功回调地址
//            'attach',//自定义参数，支付通知中原样返回
//            'subject', //订单描述
//            'spbill_create_ip',//用户的客户端IP
//            'pay_info', //其他的支付信息
//
//        ];


        $payParam['spbill_create_ip'] = gethostbyname($_SERVER['SERVER_NAME']);
		$payParam['user_ip']          = Utils::getClientIp();

        $client = new \Yunshop\ServiceProviderConnect\common\ClientRequest();

        $client->setMethod('/api/open/plugins/payment/pre_order');

        $client->switchLog(true);

        $client->setAllParameter($payParam);

        $result = $client->post();

        if ($result['status']) {
            return $result['data'];
        }

        throw new AppException($result['msg']);

    }

    public static function refund($data)
    {
        $client = new \Yunshop\ServiceProviderConnect\common\ClientRequest();

        $client->setMethod('/api/open/plugins/payment/refund');

        $client->switchLog(true);

//        $client->setParameter('total_money', bcmul($data['total_money'], 100,0));
        $client->setParameter('out_trade_no', $data['pay_sn']);
        $client->setParameter('refund_amount', bcmul($data['refund_amount'], 100,0)); //单位-分
        $client->setParameter('spbill_create_ip', gethostbyname($_SERVER['SERVER_NAME']));
        $client->setParameter('refund_reason', '订单退款');

        $result = $client->post();

        if ($result['status']) {
            return $result['data'];
        }

        throw new AppException($result['msg']);
    }

    public static function payNotice($noticeData,$is_share = 0)
    {
        $data =  $noticeData;

        \Log::debug("---服务商系统聚合支付回调参数---",$noticeData);

        if (empty($data)) {
            throw new AppException('通知支付参数为空');
        }

        if (empty($data['order_code'])) {
            throw new AppException('支付号不存在');
        }


        if (empty(\YunShop::app()->uniacid)) {
            if ($data['attach']) {
                \Setting::$uniqueAccountId = \YunShop::app()->uniacid =  $data['attach'];
            }
            \Log::debug('---------attach数组--------', \YunShop::app()->uniacid);
        }

        //是否分账支付是则验证子商户账号
        if ($is_share) {
            $shareMerchant = ShareMerchant::where('pay_merchant_no', $data['payment_store_code'])->first();

            if (!$shareMerchant) {
                throw  new AppException('商城不存在该商户号');
            }

        } else {
            $paySet =  \Setting::get('payment.integration_pay');

            if ($paySet['account_info']['merchant_no'] != $data['payment_store_code']) {
                throw  new AppException('通知商户和商城配置商户不相等');
            }
        }


        return [
            'pay_data' => [
                'total_fee' => $data['pay_price'],
                'out_trade_no' => $data['order_code'],
                'trade_no' => $data['order_code'],
                'unit' => 'fen'
            ],
            'response' => 'success'
        ];
    }
}