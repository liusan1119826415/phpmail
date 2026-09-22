<?php


namespace app\frontend\modules\project\models;

use app\common\models\project\FloorSpace as BaseFloorSpace;
class FloorSpace extends BaseFloorSpace
{
    /**
     *  定义字段名
     * 可使
     * @return array */
    public function atributeNames() {
        return [
            'name'=> '空间名称',
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


    public static function updateActivateSpace(int $floorId,int $spaceId)
    {
        self::where('floor_id', $floorId)
            ->where('id', $spaceId)
            ->update(['activate' => 1]);
    }

    public static function getFirstSpace(int $floorId)
    {
        $firstSpace = FloorSpace::where('floor_id', $floorId)
            ->orderBy('id', 'asc')
            ->first();
        return $firstSpace;
    }

    public static function updateOtherActivate(int $project_id,int $space_id)
    {
        return self::where('project_id', $project_id)
            ->where('id', '!=', $space_id)
            ->update(['activate' => 0]);
    }
}