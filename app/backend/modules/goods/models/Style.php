<?php
namespace app\backend\modules\goods\models;

/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/2/27
 * Time: 上午9:18
 */


class Style extends \app\common\models\goods\GoodsStyle
{
    static protected $needLog = true;

    /**
     * @param $id
     * @return mixed
     */
    public static function deleted($id)
    {
        return self::where('id', $id)
            ->delete();
    }

    /**
     *  定义字段名
     * 可使
     * @return array */
    public  function atributeNames() {
        return [
            'name'=> '风格名称',
            'type'=>'类型'
        ];
    }
    
    /**
     * 字段规则
     * @return array */
    public  function rules() {
        return [
            'name' => 'required',
            'type' => 'required',
        ];
    }


}