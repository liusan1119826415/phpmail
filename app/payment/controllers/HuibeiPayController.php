<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/3/28
 * Time: 上午6:50
 */

namespace app\payment\controllers;

use app\common\models\OrderPay;
use app\common\services\Pay;
use app\common\services\PayFactory;
use app\payment\PaymentController;
use Yunshop\HuiBeiPay\services\payment\HuibeiService;


class HuibeiPayController extends PaymentController
{

    private $payData;

    private $typeName;

    public function preAction()
    {
        parent::preAction();
        if(!$this->getResponseResult()){
            die('验签失败或支付记录不存在!');
        }
    }

    public function notifyUrlCode()
    {
        try {
            $this->typeName='慧呗支付';
            $this->log();
            if ($this->payData['data']['state'] == 'success') {
                $pay_type_id = PayFactory::HUIBEI_CODE;
                $data = [
                    'total_fee'    =>$this->payData['data']['amount'] ? : 0 ,
                    'out_trade_no' => $this->payData['data']['sequence'],
                    'trade_no'     => $this->payData['data']['serial'],
                    'unit'         => 'yuan',
                    'pay_type'     => '慧呗支付',
                    'pay_type_id'     => $pay_type_id,
                ];
                $this->payResutl($data);
            }
            die("SUCCESS");
        } catch (\Exception $e) {
            \Log::debug('-----慧呗支付-----'.$e->getMessage(),[request()->all()]);
            die($e->getMessage());
        }
    }

    public function notifyUrlWechat()
    {
        try {
            $this->typeName='微信-慧呗支付';
            $this->log();
            if ($this->payData['data']['state'] == 'success') {
                $pay_type_id = PayFactory::HUIBEI_WECHAT;
                $data = [
                    'total_fee'    =>$this->payData['data']['amount'] ? : 0 ,
                    'out_trade_no' => $this->payData['data']['sequence'],
                    'trade_no'     => $this->payData['data']['serial'],
                    'unit'         => 'yuan',
                    'pay_type'     => '微信-慧呗支付',
                    'pay_type_id'     => $pay_type_id,
                ];
                $this->payResutl($data);
            }
            die("SUCCESS");
        } catch (\Exception $e) {
            \Log::debug('-----微信-慧呗支付-----'.$e->getMessage(),[request()->all()]);
            die($e->getMessage());
        }
    }


    public function notifyUrlAlipay()
    {
        try {
            $this->typeName='支付宝-慧呗支付';
            $this->log();
            if ($this->payData['data']['state'] == 'success') {
                $pay_type_id = PayFactory::HUIBEI_ALIPAY;
                $data = [
                    'total_fee'    =>$this->payData['data']['amount'] ? : 0 ,
                    'out_trade_no' => $this->payData['data']['sequence'],
                    'trade_no'     => $this->payData['data']['serial'],
                    'unit'         => 'yuan',
                    'pay_type'     => '支付宝-慧呗支付',
                    'pay_type_id'     => $pay_type_id,
                ];
                $this->payResutl($data);
            }
            die("SUCCESS");
        } catch (\Exception $e) {
            \Log::debug('-----支付宝-慧呗支付-----'.$e->getMessage(),[request()->all()]);
            die($e->getMessage());
        }
    }



    /**
     * 支付日志
     */
    public function log()
    {
        //访问记录
        Pay::payAccessLog();
        //保存响应数据
        Pay::payResponseDataLog($this->payData['data']['sequence'], $this->typeName, json_encode($this->payData));
    }

    /**
     * 获取回调结果
     *
     * @return array|mixed|\stdClass
     */
    public function getResponseResult()
    {
        $res_data=file_get_contents('php://input');
        parse_str($res_data,$resData);
        $resData['data']=json_decode($resData['data'],true);
        $pay=OrderPay::where('pay_sn',$resData['data']['sequence'])->first();
        if($pay){
            $this->payData=$resData;
            \Setting::$uniqueAccountId =\YunShop::app()->uniacid = $pay->member->uniacid;
            if((new HuibeiService())->verifySign($resData['data'],$resData['sign'])){
                return true;
            }else{
                \Log::debug('慧呗支付验签失败!');
                return false;
            }
        }else{
            \Log::debug($resData['data']['sequence'].'支付记录不存在!');
            return false;
        }

    }



}
