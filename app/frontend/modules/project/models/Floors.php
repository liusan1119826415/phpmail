<?php


namespace app\frontend\modules\project\models;

use app\common\models\project\Floors as BaseFloors;
class Floors extends BaseFloors
{
    /**
     *  定义字段名
     * 可使
     * @return array */
    public function atributeNames() {
        return [
            'name'=> '楼层名称',
        ];
    }

    /**
     * 字段规则
     * @return array */
    public function rules() {

        return [
            'name' => 'required',
        ];
    }

    public static function getFloor(int $id,int $project_id)
    {
        return self::where('project_id',$project_id)->where('id',$id)->first();
    }

    public static function updateActivateFloor(int $projectId,int $floorId)
    {
        return self::where('project_id', $projectId)
            ->where('id', $floorId)
            ->update(['activate' => 1]);
    }

    public static function getFirstFloor(int $projectId)
    {
          return self::where('project_id', $projectId)
              ->orderBy('id', 'asc')
              ->first();
    }

    public static function updateOtherActivate(int $project_id,int $floor_id)
    {
       return self::where('project_id', $project_id)
            ->where('id', '!=', $floor_id)
            ->update(['activate' => 0]);
    }

}