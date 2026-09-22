<?php

namespace app\exports\action;

use app\backend\modules\finance\models\Balance;


class BalanceRecord extends BaseAction
{
    public $type = 'balanceRecord';

    public $name = '余额明细';


    public function getData($request): \Generator
    {
        $search = $request->search;
        $list = Balance::records()->search($search)->searchMember($search);
        $shopSet = \Setting::get('shop.member');
        $list = $list->select('yz_balance.*')->get();
        foreach ($list as $key => $item) {
            if ($item->member) {
                $member_id = $item->member->uid;
                $member_name = $item->member->realname ?: $item->member->nickname;
                $member_mobile = $item->member->mobile;
                $member_level = $shopSet['level_name'];
                $member_group = '无分组';
                if ($item->member->yzMember->group) {
                    $member_group = $item->member->yzMember->group->group_name ?: '无分组';
                }
                if ($item->member->yzMember->level) {
                    $member_level = $item->member->yzMember->level->level_name ?: $shopSet['level_name'];
                }
            } else {
                $member_id = '';
                $member_name = '';
                $member_mobile = '';
                $member_level = $shopSet['level_name'];
                $member_group = '无分组';
            }
            yield $key => [
                'time' => $item->created_at,
                'member_id' => $member_id,
                'name' => $member_name,
                'mobile' => $member_mobile,
                'level' => $member_level,
                'group' => $member_group,
                'order_sn' => $item->serial_number,
                'type' => $item->service_type_name,
                'income_type' => $item->type_name,
                'before_money' => $item->old_money,
                'money' => $item->change_money,
                'after_money' => $item->new_money,
                'remake' => $item->remark
            ];
        }
    }


}
