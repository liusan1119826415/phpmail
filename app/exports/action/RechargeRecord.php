<?php

namespace app\exports\action;

use app\backend\modules\finance\models\BalanceRechargeRecords;

class RechargeRecord extends BaseAction
{
    public $type = 'rechargeRecord';

    public $name = '余额充值记录';


    public function getData($request): \Generator
    {
        $records = BalanceRechargeRecords::records();
        $search = $request->search;
        if ($search) {
            $records = $records->search($search);
        }
        $list = $records->orderBy('created_at', 'desc')->get();
        foreach ($list as $key => $item) {
            switch ($item->status) {
                case 1:
                    $item->status = '充值成功';
                    break;
                case -1:
                    $item->status = '充值失败';
                    break;
                default:
                    $item->status = '申请中';
                    break;
            }
            yield $key => [
                'recharge_sn' => $item->ordersn,
                'nickname' => $item->member->nickname,
                'member_id' => $item->member->uid,
                'mobile' => $item->member->mobile,
                'level' => $item->member->yzMember->level->level_name,
                'group' => $item->member->yzMember->group->group_name,
                'time' => $item->created_at,
                'type' => $item->type_name,
                'money' => $item->money,
                'status' => $item->status,
                'remark' => $item->remark,
            ];
        }
    }


}
