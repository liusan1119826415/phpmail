<?php


namespace app\backend\modules\goods\models;


class GoodsStyleRelations extends \app\common\models\goods\GoodsStyleRelations
{

    /**
     * 保存工艺材质以及商品风格
     */

    public static function saveStyle($goods_id,$data,$type)
    {
        if($data){
            self::where('goods_id',$goods_id)->where('type',$type)->delete();
            $new_data = [];
            foreach ($data as$key=> $item)
            {
                $new_data[] = [
                  "goods_id"=>$goods_id,
                  "type"=>$type,
                  "style_id"=>$item
                ];
            }

            return self::insert($new_data);
        }
    }



}