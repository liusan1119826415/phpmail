<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/6/12
 * Time: 15:16
 */

namespace app\frontend\modules\finance\controllers;

use app\common\services\PayFactory;
use app\common\components\ApiController;
use app\frontend\models\Member;
use app\frontend\modules\finance\balance\PreBalanceRecharge;
use app\frontend\modules\finance\models\Balance;
use app\frontend\modules\finance\payment\types\RechargePaymentTypes;
use app\frontend\modules\finance\services\BalanceRechargeService;

class BalanceRechargeController extends ApiController
{

    public function page()
    {

        $rechargeService = new BalanceRechargeService();

        $data  = [
            'credit2' => $this->getMemberInfo()->credit2,
            'display_switch' => $rechargeService->getDisplaySwitch(),
            'buttons' => app('Payment')->setPaymentTypes(new RechargePaymentTypes())->getPaymentButton(),
            'activity'=> $rechargeService->getActivity(),
            'plugins' => $rechargeService->getPlugin(),
        ];


        return $this->successJson('recharge',$data);
    }

    //预充值
    public function pre()
    {
        if (empty(request()->input('recharge_money')) || request()->input('recharge_money') == 'NaN') {
            return $this->errorJson('充值金额不能为空,请填写充值金额');
        }

        $balanceRecharge = new PreBalanceRecharge();

        $balanceRecharge->init($this->getMemberInfo(), request());



        $result = [
            'total_discount_amount' => $balanceRecharge->getDiscounts()->sum('amount'),
            'total_coupon_amount' => $balanceRecharge->getCoupons()->sum('amount'),
            'recharge' => $balanceRecharge,
            'member_coupon_list' => $balanceRecharge->getCalculator()->getOptionalCoupons(),
        ];

//        dd($balanceRecharge);


        return $this->successJson('pre', $result);
    }


    //确认充值
    public function confirm()
    {
        return $this->errorJson('不可用');

        if (empty(request()->input('recharge_money')) || request()->input('recharge_money') == 'NaN') {
            return $this->errorJson('充值金额不能为空,请填写充值金额');
        }

        if (!request()->input('pay_type')) {
            return $this->errorJson('支付方式不能为空');
        }

        $balanceRecharge = new PreBalanceRecharge();

        $balanceRecharge->init($this->getMemberInfo(), request());

        $balanceRecharge->generate();

        //这里需要补充调用支付类处理
        //不这样做原因是余额充值的支付有点乱先用旧的支付接口，没时间去整理这是客户的需求开发有时间限制

        return $this->successJson('create', $balanceRecharge);
    }


    //获取会员信息
    private function getMemberInfo()
    {
        return $this->memberInfo = Member::current();
    }


}