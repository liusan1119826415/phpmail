<?php

namespace app\exports\action;


use app\backend\modules\member\models\Member;

class MemberPoint extends BaseAction
{
    public $type = 'memberPoint';

    public $name = '会员积分';


    public function getData($request): \Generator
    {
        $recordsModels = Member::searchMembers($request, 'credit1');
        $recordsModels->orderBy('uid', 'desc');
        foreach ($recordsModels->get() as $key => $item) {
            yield $key => [
                'time' => date('Y-m-d H:i:s', $item->createtime),
                'member_id' => $item->uid,
                'nickname' => strpos($item->nickname, '=') === 0 ? ' ' . $item->nickname : $item->nickname,
                'name' => strpos($item->realname, '=') === 0 ? ' ' . $item->realname : $item->realname,
                'mobile' => $item->mobile,
                'money' => $item->credit1
            ];
        }
    }


}
