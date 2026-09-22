<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/11/13
 * Time: 17:45
 */

namespace app\backend\modules\setting\controllers;


use app\backend\modules\integrationPay\models\IntegrationPayWithdraw;
use app\common\components\BaseController;
use app\backend\modules\integrationPay\services\LklIntegrationPayService;
use app\common\services\payment\IntegrationPay;

class IntegrationPayController extends BaseController
{

    public function updPayAccount()
    {
        $result = IntegrationPay::getConfig();

        if($result && $result['status']){

            \Setting::set('payment.integration_pay.account_info', $result['data']);

            return $this->successJson('info', $result['data']);
        }

        return $this->errorJson('更新失败:'.$result['msg']);
    }

    public function preWithdraw()
    {
        $merchant_no = request()->input('merchant_no');

        $result = (new LklIntegrationPayService())->preWithdraw($merchant_no);

        if($result && $result['status']) {
            return $this->successJson('pre',$result['data']);
        }

        return $this->errorJson($result['msg']);
    }

    public function withdraw()
    {
        $merchant_no = request()->input('mer_id');
        $amount = request()->input('amount');
        $payType = request()->input('pay_type');
        $plugin_id = request()->input('plugin_id', 0);

        (new LklIntegrationPayService())->withdraw([
            'amount'=> $amount,
            'pay_type'=>$payType,
            'merchant_no' => $merchant_no,
            'plugin_id' =>$plugin_id,
        ]);

        return $this->successJson('success');
    }

    public function withdrawLog()
    {
        $account_info = \Setting::get('payment.integration_pay.account_info');
        $data = [
            'merchant_no' => $account_info['merchant_no'],
        ];
        return view('setting.pay.lklpay-withdraw', [
            'data' => json_encode($data),
        ])->render();
    }

    public function withdrawList()
    {
        $search = request()->input('search');
        $list = IntegrationPayWithdraw::getList($search)->orderBy('id', 'desc')->paginate(15);

//        if ($list->isNotEmpty()) {
//            $list->map(function ($item) {
//
//            });
//        }

        return $this->successJson('list', $list);
    }
}