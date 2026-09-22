<?php
namespace app\backend\modules\goods\models;

/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/2/27
 * Time: 上午9:18
 */


class PptTemplate extends \app\common\models\goods\PptTemplate
{

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
            'name'=> '模版名称',
            'url'=>'模版url'
        ];
    }
    
    /**
     * 字段规则
     * @return array */
    public  function rules() {
        return [
            'name' => 'required',
            'url' => 'required',
        ];
    }


}