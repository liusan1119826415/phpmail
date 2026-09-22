<?php

namespace app\exports\action;

use app\exports\action\Member as MemberAction;
use app\backend\modules\member\models\Member;
use app\backend\modules\member\models\MemberChildren;

use app\common\services\Session;
use Illuminate\Support\Facades\DB;



class MemberAgent extends MemberAction
{
    public $type = 'memberAgent';

    public $name = '会员直推';


    public function getData($request): \Generator
    {
        $list = Member::getAgentInfoByMemberId($request);
        $status_name = [
            0 => "未审核",
            1 => "审核中",
            2 => "已审核"
        ];
        $inviter_name = [
            0 => "暂定下线",
            1 => "锁定关系下线"
        ];

        foreach ($list->get() as $key => $value) {
            $value = $value->toArray();
            if (empty($value['has_one_fans']['followed'])) {
                if (empty($value['has_one_fans']['uid'])) {
                    $follow = "未关注";
                } else {
                    $follow = "取消关注";
                }
            } else {
                $follow = "已关注";
            }
            yield $key => [
                'member_id' => $value['uid'],
                'parent' => $value['yz_member']['agent']['nickname'],
                'child' => $value['nickname'],
                'name' => $value['realname'],
                'mobile' => $value['mobile'],
                'status' => $value['yz_member']['is_agent'] == 0 ? "-" : $status_name[$value["yz_member"]['status']],
                'child_status' => $inviter_name[$value['yz_member']['inviter']],
                'register_time' => date("Y-m-d H:i", $value['createtime']),
                'followed' => $follow
            ];
        }
    }

    /**
     * 获取$level级下线人数超过$countNum的会员id
     * @param int $countNum
     * @param string $level
     * @return array
     */
    public function getChildCount($countNum = 0, $level = '')
    {
        $model = MemberChildren::uniacid()->select(DB::raw('member_id,COUNT(*) as count_num'));
        if (!empty($level)) {
            $model->where('level', $level);
        }
        $ids = $model->groupBy('member_id')->having('count_num', '>=', $countNum)
            ->get()->toArray();
        return array_column($ids, 'member_id');
    }

}
