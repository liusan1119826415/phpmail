<?php

namespace app\exports\action;

use app\backend\modules\member\models\MemberParent;
use app\exports\action\Member as MemberAction;
use app\backend\modules\member\models\Member;
use app\backend\modules\member\models\MemberChildren;

use app\common\services\Session;
use Illuminate\Support\Facades\DB;
use Yunshop\TeamDividend\models\TeamDividendLevelModel;


class MemberParentFirst extends MemberAction
{
    public $type = 'memberParentFirst';

    public $name = '会员直推上级';


    public function preResult($header,$columns): array
    {
        $level_list = [];
        if (app('plugins')->isEnabled('team-dividend')) {
            $team_list = TeamDividendLevelModel::getList()->get();
            foreach ($team_list as $level) {
                $level_list[$level->id] = $level->level_name;
            }
        }
        $header = $level_list + $header;
        $columns = array_merge(array_keys($level_list), $columns);
        return [$header,$columns];
    }

    public function getData($request): \Generator
    {
        $member_id = $request->id;
        $levelId = [];
        if (app('plugins')->isEnabled('team-dividend')) {
            $team_list = TeamDividendLevelModel::getList()->get();
            foreach ($team_list as $level) {
                $levelId[] = $level->id;
            }
        }
        $child = MemberParent::where('member_id', $member_id)
            ->where('level', 1)
            ->with(['hasManyParent' => function ($q) {
                $q->orderBy('level', 'asc');
            }])
            ->get();
        foreach ($child as $key => $item) {
            $level = $this->getLevel($item, $levelId);
            yield $key => $level + [
                    'member_id' => $item->member_id,
                    'nickname' => $item->hasOneMember->nickname,
                    'name' => $item->hasOneMember->realname . '/' . $item->hasOneMember->mobile,
                ];
        }
    }

    public function getLevel($member, $levelId)
    {
        $data = [];
        foreach ($levelId as $k => $value) {
            foreach ($member->hasManyParent as $key => $parent) {
                if ($parent->hasOneTeamDividend->hasOneLevel->id == $value) {
                    $data[$value] = $parent->hasOneMember->nickname . ' ' . $parent->hasOneMember->realname . ' ' . $parent->hasOneMember->mobile;
                    break;
                }
            }
            $data[$value] = $data[$value] ?: '';
        }
        return $data;
    }

}
