<?php

namespace app\exports\action;

use app\backend\modules\member\models\MemberParent;
use app\exports\action\Member as MemberAction;
use app\backend\modules\member\models\Member;
use app\backend\modules\member\models\MemberChildren;

use app\common\services\Session;
use Illuminate\Support\Facades\DB;
use Yunshop\TeamDividend\models\TeamDividendAgencyModel;
use Yunshop\TeamDividend\models\TeamDividendLevelModel;


class MemberTeam extends MemberAction
{
    public $type = 'memberTeam';

    public $name = '会员团队';


    public function preResult($header,$columns): array
    {
        $level_list = [];
        if (app('plugins')->isEnabled('team-dividend')) {
            $level_list['team_level'] = '经销商等级';
            $team_dividend_levels = TeamDividendLevelModel::uniacid()->select('level_name', 'id')->orderBy('level_weight', 'ASC')->get()->toArray();
            foreach ($team_dividend_levels as $v) {
                $level_list[$v['id']] = '等级(' . $v['level_name'] . ')人数';
            }
        }
        $header = $header + $level_list;
        $columns = array_merge($columns, array_keys($level_list));
        return [$header,$columns];
    }


    public function getData($request): \Generator
    {
        if (app('plugins')->isEnabled('team-dividend')) {
            $team_dividend_levels = TeamDividendLevelModel::uniacid()->select('level_name', 'id')->orderBy('level_weight', 'ASC')->get()->toArray();
            $agent_list = TeamDividendAgencyModel::uniacid()
                ->whereHas('hasOneMemberParent', function ($query) use ($request) {
                    $query->where('parent_id', $request->id);
                })->select('uid', 'level')->get();
        } else {
            $team_dividend_levels = [];
            $agent_list = null;
        }
        $list = MemberParent::children($request)
            ->orderBy('yz_member_parent.level', 'asc')
            ->orderBy('yz_member_parent.id', 'asc')
            ->get();
        foreach ($list as $k=>$v) {
            $child_ids = MemberChildren::uniacid()->where('yz_member_children.member_id', $v->member_id)
                ->join('yz_member', function ($join) {
                    $join->on('yz_member_children.child_id', '=', 'yz_member.member_id')->whereNull('deleted_at');
                })->pluck('child_id')->toArray();
            $team_dividend_level_data = [];
            if ($team_dividend_levels) {
                //获取当前用户经销商等级
                $levelColumn = '';
                if ($currentLevel = $agent_list->where('uid', $v->member_id)->first()) {
                    $index = array_search($currentLevel->level, array_column($team_dividend_levels, 'id'));
                    if ($index !== false) {
                        $levelColumn = $team_dividend_levels[$index]['level_name'];
                    }
                }
                $team_dividend_level_data['team_level'] = $levelColumn;
                $this_agent_list = $agent_list->whereIn('uid', $child_ids);
                foreach ($team_dividend_levels as $k => $vv) {
                    $team_dividend_level_data[$vv['id']] = $this_agent_list->where('level', $vv['id'])->count() ?: '0';
                }

            }
            yield $k => [
                    'member_id' => $v->member_id,
                    'nickname' => $this->changeSpecialSymbols($v->hasOneChildMember->nickname ?: ''),
                    'name' => $v->hasOneChildMember->realname ?: '',
                    'mobile' => $v->hasOneChildMember->mobile ?: '',
                    'register_time' => $v->hasOneChildMember->createtime ? date('Y-m-d H:i:s', $v->hasOneChildMember->createtime) : '',
                    'performance' => $v->order_price_sum ?: '0',
                    'fans' => count($child_ids) ?: '0',
                    'parent' => $this->changeSpecialSymbols($v->hasOneChildYzMember->hasOneParent->nickname ?: ''),
                    'parent_id' => $v->hasOneChildYzMember->hasOneParent->uid ?: '',
                ] + $team_dividend_level_data;
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

    protected function changeSpecialSymbols($str)
    {
        $regex = "/\/|\～|\，|\。|\！|\？|\“|\”|\【|\】|\『|\』|\：|\；|\《|\》|\’|\‘|\ |\·|\~|\!|\@|\#|\\$|\%|\^|\&|\(|\)|\_|\+|\{|\}|\:|\<|\>|\?|\[|\]|\,|\.|\/|\;|\'|\`|\-|\=|\\\|\|/";
        return preg_replace($regex, '', $str);
    }

}
