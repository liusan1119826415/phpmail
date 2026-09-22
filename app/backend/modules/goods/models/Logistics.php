<?php
/**
 * 物流运输配置
 * Created by PhpStorm.
 */

namespace app\backend\modules\goods\models;


class Logistics extends \app\common\models\project\Logistics
{

    public static function quickUpdatedDispatch($id, $type,$status)
    {
        return self::where('id', $id)->update([$type => $status]);
    }

}