<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/11/14
 * Time: 10:02
 */

namespace app\backend\modules\integrationPay\services;


use app\backend\modules\integrationPay\models\IntegrationPayWithdraw;
use app\common\exceptions\AppException;

class LklIntegrationPayService
{
    public function __construct()
    {

    }

    public function preWithdraw($merchant_no)
    {
        $client = new \Yunshop\ServiceProviderConnect\common\ClientRequest();
        $client->setMethod('/api/open/plugins/payment/withdraw/prepare');
        $client->setParameter('payment_store_code', $merchant_no);

        return $client->post();
    }

    //todo laltest
    public function defaultData()
    {
        return [
            'memo' => '交易成功',
            'draw_state' => 'DRAW.SUCCESS',
            "withdraw_created_time" => "2023-11-14T09:06:50.046Z",
            "withdraw_complete_time" => "2023-11-14T09:06:50.046Z",
            "wallet_id" => "112208441024",
            "draw_jnl" => "220406170105809334963032",
            "draw_amt" => 2.00,
            "draw_fee" => 1.00,
            "draw_mode" => "D0",
            "batch_auto_settle" => "01",
            "acct_no" => "621226*********5095",
            "acct_name" => "*东玄",
            "nbk_name" => "招商银行上海分行东方支行",
        ];
    }

    //提现
    public function withdraw($data)
    {
        if (empty($data['amount']) || $data['amount'] <= 0) {
            throw new AppException('提现金额参数错误');
        }

        if (empty($data['merchant_no'])) {
            throw new AppException('未知提现商户');
        }


        $request = [
            'payment_store_code' => $data['merchant_no'],
            'draw_amt' => $data['amount'],
            'pay_type' => $data['pay_type'],
        ];

        $client = new \Yunshop\ServiceProviderConnect\common\ClientRequest();
        $client->setMethod('/api/open/plugins/payment/withdraw');
        $client->setAllParameter($request);
        $result = $client->post();

//        $result = [
//            'status' => true,
//            'data' => $this->defaultData(),
//        ];

        if($result && $result['status']){

            $resultData = $result['data'];

            $withdrawLog = new IntegrationPayWithdraw(['uniacid' => \YunShop::app()->uniacid]);

            $createData = [
                'plugin_id' => $data['plugin_id'],
                'merchant_no' => $data['merchant_no'],
                'wallet_id'   => $resultData['wallet_id'],
                'withdraw_sn' => $resultData['draw_jnl'],
                'amount'      => $resultData['draw_amt'],
                'fee_rate'    => $resultData['draw_fee'],
                'draw_mode'   => $resultData['draw_mode'],
                'settle_type' => $resultData['batch_auto_settle'],
                'draw_status' => $resultData['draw_status'],
                'details' => $resultData,
            ];

            if ($resultData['withdraw_complete_time']) {
                $createData['complete_at'] = strtotime($resultData['withdraw_complete_time']);
            }

            $withdrawLog->fill($createData);
            $withdrawLog->save();

            return true;
        } else {

            throw new AppException($result['msg']);
        }


    }
}