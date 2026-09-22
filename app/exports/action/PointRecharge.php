<?php

namespace app\exports\action;

use app\backend\modules\point\models\RechargeModel;

class PointRecharge extends BaseAction
{
    public $type = 'pointRecharge';

    public $name = '积分充值记录';


    public function getData($request): \Generator
    {
        $recordsModels = RechargeModel::uniacid()->with('member');
        if ($search = $request->search) {
            $recordsModels = $recordsModels->search($search);
        }
        $recordsModels->orderBy('id', 'desc');
        foreach ($recordsModels->get() as $key => $item) {
            yield $key => [
                'time' => $item->created_at,
                'member_id' => $item->member->uid,
                'nickname' => $item->member->nickname,
                'name' => $item->member->realname,
                'mobile' => $item->member->mobile,
                'recharge_sn' => $item->order_sn,
                'money' => $item->money,
                'type' => $item->type_name,
                'status' => $item->status == 1 ? "充值成功" : "充值失败",
                'remark' => $item->remark,
            ];
        }
    }


}
