<?php

namespace app\exports\action;

use app\backend\modules\finance\models\PointLog;

class PointRecord extends BaseAction
{
    public $type = 'pointRecord';

    public $name = '积分明细';


    public function getData($request): \Generator
    {
        $recordsModels = PointLog::uniacid()->with(['member:uid,nickname,realname,mobile']);

        if ($search = $request->search) {
            $recordsModels = $recordsModels->search($search);
        }
        foreach ($recordsModels->orderBy('id', 'desc')->get() as $key => $item) {
            yield $key => [
                'time'=>$item->created_at,
                'member_id'=>$item->member_id,
                'nickname'=>$item->member->nickname,
                'name'=>$item->member->realname,
                'mobile'=>$item->member->mobile,
                'type'=>$item->source_name,
                'before_money'=>$item->before_point,
                'money'=>$item->point,
                'after_money'=>$item->after_point,
                'remark'=>$item->remark,
                'member_remark'=>$this->getRemark($item),
            ];
        }
    }

    /**
     * 获取会员备注
     * @param $remark
     * @return false|string
     */
    private function getRemark($item)
    {
        $member_remark = '';
        if (in_array($item->point_mode,[13,14])) {
            $member_remark = strstr($item->remark,'会员备注:') ? substr(strstr($item->remark,'会员备注:'),13) : null;
        }
        return $member_remark;
    }


}
