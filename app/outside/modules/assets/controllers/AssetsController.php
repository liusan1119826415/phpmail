<?php

namespace app\outside\modules\assets\controllers;

use app\common\models\Member;
use app\common\services\finance\BalanceChange;
use app\common\services\finance\PointService;
use app\outside\controllers\OutsideController;
use Darabonba\GatewaySpi\Models\InterceptorContext\request;

class AssetsController  extends OutsideController
{
    public $member;
    public function paramValidate()
    {
        $this->validate([
            'mobile' => 'required|regex:/^1[3456789]\d{9}$/',
            'change_type' => 'required|integer',
            'amount' => 'required|numeric|min:0|regex:/^\d+(\.\d{1,2})?$/',
        ],request(), [],[
            'mobile' => '手机号',
            'change_type' =>  '操作类型',
            'amount' =>  '变动数量',
        ]);
        $mobile = request()->input('mobile');

        $this->member = $member = Member::uniacid()
            ->where('mobile', $mobile)
            ->whereHas('yzMember', function ($query) {
                $query->whereNull('deleted_at');
            })
            ->first();
        if (empty($member)) {
            return $this->errorJson('会员不存在');
        }
    }

    public function pointChange()
    {
        $this->paramValidate();
        $amount = request()->input('amount');
        $change_type = request()->input('change_type');
        if ($change_type == 1) {
            $type = PointService::POINT_INCOME_GET;
        } elseif ($change_type == 2) {
            if ($this->member['credit1'] < $amount) {
                return $this->errorJson('会员积分不足');
            }
            $type = PointService::POINT_INCOME_LOSE;
        } else {
            return $this->errorJson('操作类型格式不正确');
        }

        $point_data = [
            'point_income_type' => $type,
            'member_id' => $this->member['uid'],
            'point_mode' => 198,
            'point' => $amount,
            'remark' => "商城统一接口导入"
        ];

        (new PointService($point_data))->changePoint();
        return $this->successJson('成功');
    }

    public function balanceChange()
    {
        $this->paramValidate();
        $amount = request()->input('amount');
        $change_type = request()->input('change_type');
        if ($change_type == 1) {
            $type = \app\common\services\credit\ConstService::TYPE_INCOME;
        } elseif ($change_type == 2) {
            if ($this->member['credit2'] < $amount) {
                return $this->errorJson('会员余额不足');
            }
            $type = \app\common\services\credit\ConstService::TYPE_EXPENDITURE;
        } else {
            return $this->errorJson('操作类型错误');
        }
        $data = [
            'member_id' => $this->member['uid'],
            'change_value' => $amount,
            'operator' => \app\common\services\credit\ConstService::OPERATOR_SHOP,
            'operator_id' => 1,
            'remark' => '商城统一接口导入',
            'relation' => '',
            'source' => 147,
            'type' => $type,
        ];

        (new BalanceChange())->universal($data);
        return $this->successJson('成功');
    }

}