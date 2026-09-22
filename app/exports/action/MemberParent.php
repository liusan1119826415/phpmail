<?php

namespace app\exports\action;

use app\common\services\ExportService;
use app\exports\action\Member as MemberAction;
use app\backend\modules\member\models\Member;
use app\backend\modules\member\models\MemberChildren;

use app\common\services\Session;
use Illuminate\Support\Facades\DB;


class MemberParent extends MemberAction
{
    public $type = 'memberParent';

    public $name = '会员上级';


    public function getData($request): \Generator
    {
        $export_data = [];
        $member_id = $request->id;
        $child = \app\backend\modules\member\models\MemberParent::where('member_id', $member_id)->with(['hasOneMember']);
        foreach ($child->get() as $key => $item) {
            $member = $item->hasOneMember;
            yield $key => [
                'member_id' => $member->uid,
                'nickname' => changeSpecialSymbols($member->nickname),
                'name' => $member->realname,
                'mobile' => $member->mobile
            ];
        }
    }
}
