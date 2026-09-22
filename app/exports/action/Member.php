<?php

namespace app\exports\action;

use app\backend\modules\member\models\MemberChildren;
use app\backend\modules\member\services\HandleNickname;
use app\common\services\Session;
use Illuminate\Support\Facades\DB;

class Member extends BaseAction
{
    public $type = 'member';

    public $name = '会员';

    public function preValidate(): bool
    {
        $set = \Setting::getByGroup('pay_password')['withdraw_verify'] ?: [];
        if (empty($set) || empty($set['is_member_export_verify'])) {
            return true;
        }
        $verify = Session::get('withdraw_verify');  //没获取到
        if ($verify && $verify >= time()) {
            return true;
        }
        return false;
    }


    public function getData($request): \Generator
    {
        $parames = $request;
        $member_builder = \app\backend\modules\member\models\Member::searchNewMembers($parames)->without('hasManyTag');
        if ($parames['search']['first_count'] ||
            $parames['search']['second_count'] ||
            $parames['search']['third_count'] ||
            $parames['search']['team_count']
        ) {
            $result_ids = [];
            if ($parames['search']['first_count']) {
                $result_ids = $this->getChildCount($parames['search']['first_count'], 1);
            }
            if ($parames['search']['second_count']) {
                $second_ids = $this->getChildCount($parames['search']['second_count'], 2);
                $result_ids = empty($result_ids) ? $second_ids : array_intersect($result_ids, $second_ids);
                unset($second_ids);
            }
            if ($parames['search']['third_count']) {
                $third_ids = $this->getChildCount($parames['search']['third_count'], 3);
                $result_ids = empty($result_ids) ? $third_ids : array_intersect($result_ids, $third_ids);
                unset($third_ids);
            }
            if ($parames['search']['team_count']) {
                $team_ids = $this->getChildCount($parames['search']['team_count']);
                $result_ids = empty($result_ids) ? $team_ids : array_intersect($result_ids, $team_ids);
                unset($team_ids);
            }
            $member_builder = $member_builder->whereIn('uid', $result_ids);
        }
        $handle_nickname = new HandleNickname();
        $member_builder = $member_builder->get();
        foreach ($member_builder as $key => $item) {
            $item = $item->toArray();
            if (!empty($item['yz_member']) && !empty($item['yz_member']['agent'])) {
                $agent = $handle_nickname->removeEmoji($item['yz_member']['agent']['nickname']);

            } else {
                $agent = '总店';
            }
            if (!empty($item['yz_member']) && !empty($item['yz_member']['group'])) {
                $group = $item['yz_member']['group']['group_name'];

            } else {
                $group = '';
            }

            if (!empty($item['yz_member']) && !empty($item['yz_member']['level'])) {
                $level = $item['yz_member']['level']['level_name'];

            } else {
                $level = '';
            }

            $order = $item['has_one_order']['total'] ?: 0;
            $price = $item['has_one_order']['sum'] ?: 0;

            if (!empty($item['has_one_fans'])) {
                if ($item['has_one_fans']['followed'] == 1) {
                    $fans = '已关注';
                } else {
                    $fans = '未关注';
                }
            } else {
                $fans = '未关注';
            }
            if (substr($item['nickname'], 0, strlen('=')) === '=') {
                $item['nickname'] = '，' . $handle_nickname->removeEmoji($item['nickname']);
            }

            if (!$item['yz_member']['parent_id']) {
                $parent_id = 0;
            } else {
                $parent_id = $item['yz_member']['parent_id'];
            }

            if (!$item['yz_member']['agent'] || !$item['yz_member']['agent']['mobile']) {
                $parent_mobile = '';
            } else {
                $parent_mobile = $item['yz_member']['agent']['mobile'];
            }
            yield $key => [
                'member_id' => $item['uid'],
                'parent' => $agent,
                'parent_id' => $parent_id,
                'parent_mobile' => $parent_mobile,
                'child' => $item['nickname'],
                'name' => $handle_nickname->removeEmoji($item['realname']),
                'mobile' => $item['mobile'],
                'level' => $level,
                'group' => $group,
                'register_time' => date('Y-m-d H:i:s', $item['createtime']),
                'point' => $item['credit1'],
                'balance' => $item['credit2'],
                'order' => $order,
                'money' => $price,
                'followed' => $fans,
                'withdraw_mobile' => $item['yz_member']['withdraw_mobile'],
                'email' => $item['email']
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
