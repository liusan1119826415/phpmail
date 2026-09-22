<?php

namespace app\frontend\modules\project\models;
use app\common\models\project\Project as BaseProject;
class Project extends BaseProject
{


    /**
     *  定义字段名
     * 可使
     * @return array */
    public function atributeNames() {
        return [
            'uniacid'=> '平台ID',
            'member_id'=> '会员id',
            'name'=> '项目名称',
          //  'status'=> '项目状态'
        ];
    }

    /**
     * 字段规则
     * @return array */
    public function rules() {

        return [
           // 'uniacid' => 'required|integer',
            'name' => 'required',
          //  'member_id' => 'required|integer',
            //'status' => 'required|integer',
        ];
    }


    public static function getProject(int $id)
    {
        return self::where('member_id',\YunShop::app()->getMemberId())->where('id',$id)->first();
    }

    public static function updateOtherActivate(int $project_id)
    {
        $project_ids = self::where('member_id', \YunShop::app()->getMemberId())
            ->where('id', '!=', $project_id)->pluck("id")->all();

        self::where('member_id', \YunShop::app()->getMemberId())
            ->where('id', '!=', $project_id)
            ->update(['activate' => 0]);
        Floors::whereIn('project_id',$project_ids)->where('activate',1)->update(['activate' => 0]);
        FloorSpace::whereIn('project_id',$project_ids)->where('activate',1)->update(['activate' => 0]);
        return true;
    }

}