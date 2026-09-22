<?php

namespace app\exports\action;

use app\common\models\Member;

class MemberBalance extends BaseAction
{
    public $type = 'memberBalance';

    public $name = '会员余额';


    public function getData($request): \Generator
    {
        $recordsModels = Member::uniacid();
        $search = $request->search ?: [];
        if ($search) {
            $recordsModels = $recordsModels->search($search);
        }
        $recordsModels->orderBy('uid', 'desc')->withoutDeleted();
        foreach ($recordsModels->get() as $key => $item) {
            yield $key => [
                'time' => date('Y-m-d H:i:s', $item->createtime),
                'member_id' => $item->uid,
                'nickname' => $item->nickname,
                'name' => $item->realname,
                'mobile' => $item->mobile,
                'money' => $item->credit2,
            ];
        }
    }


}
