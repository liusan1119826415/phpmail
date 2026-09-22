<?php

namespace app\payment\controllers;

use app\common\models\Member;
use app\common\models\PayWithdrawOrder;
use app\payment\PaymentController;
use Yunshop\ConsolWithdraw\models\ElectronicSign;
use Yunshop\ConsolWithdraw\models\WithdrawRelation;

class ConsolWithdrawController extends PaymentController
{
    // 签约回调
    public function silentSignUrl()
    {
        $data = request()->all();

        \Log::debug('耕耘灵活用工签约回调信息', $data);
        $member = Member::where('idcard', $data['idCard'])->first();

        if (!$member) {
            \Log::debug('耕耘灵活用工签约回调用户不存在', request()->all());
            echo 'fail';
            exit();
        }

        \YunShop::app()->uniacid = \Setting::$uniqueAccountId = $member->uniacid;
        $electronicSign = ElectronicSign::where('member_id', $member->uid)->first();
        if (!$electronicSign) {
            \Log::debug('耕耘灵活用工签约回调电签记录不存在', $data);
            echo 'fail';
            exit();
        }

        if ($data['result'] !== '00') {
            $electronicSign->status = -1;
            $electronicSign->msg = $data['failReason'] ?? '签约失败';
            $electronicSign->save();
            \Log::debug('耕耘灵活用工签约回调失败', $data);
            echo 'fail';
            exit();
        }

        $electronicSign->status = 2;
        $electronicSign->msg = '签约成功';
        $electronicSign->save();
        echo 'success';
        exit();
    }

    // 提现回调
    public function payBillUrl()
    {
        \Log::debug('耕耘灵活用工提现回调信息', request()->all());
        $data = request()->all();

        $withdrawRelation = WithdrawRelation::where('third_party_no', $data['orderNo'])->first();

        if (!$withdrawRelation) {
            \Log::debug('耕耘灵活用工提现回调提现记录不存在', $data);
            echo 'fail';
            exit();
        }

        \YunShop::app()->uniacid = \Setting::$uniqueAccountId = $withdrawRelation->uniacid;
        if ($data['payDtls'][0]['result'] === "00") {
            $pay_refund_model = PayWithdrawOrder::getOrderInfo($withdrawRelation->withdraw->withdraw_sn);

            if ($pay_refund_model) {
                $pay_refund_model->status = 2;
                $pay_refund_model->trade_no = $withdrawRelation->withdraw->withdraw_sn;
                $pay_refund_model->save();

                \app\common\services\finance\Withdraw::paySuccess($withdrawRelation->withdraw->withdraw_sn);
                \Log::debug('耕耘灵活用工-银行卡提现', 'withdraw.succeeded');
            }
            echo "success";

        } else {
            $pay_refund_model = PayWithdrawOrder::getOrderInfo($withdrawRelation->withdraw->withdraw_sn);
            if ($pay_refund_model) {
                \Log::debug('耕耘灵活用工-银行卡提现', 'withdraw.failed');
                $withdrawRelation->withdraw->update(['status' => 4]);
                \app\common\services\finance\Withdraw::payFail($withdrawRelation->withdraw->withdraw_sn);
            }
            echo "fail";
        }
    }
}
