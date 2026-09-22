<?php


namespace app\common\models\goods;

use app\common\models\BaseModel;
class GoodsImages extends BaseModel
{
    protected $table = 'yz_goods_images';

    public $timestamps = true;

    public $fillable = ["goods_id","image_type","high_url","compressed_url"];


    public static function saveImages($data,$goods_id)
    {
        $new_data = [];
        self::where('goods_id',$goods_id)->delete();
        if($data){
            foreach ($data as $key=>$item){
                $new_data[$key]['goods_id'] = $goods_id;
                $new_data[$key]['image_type'] = $item['image_type'];
                $new_data[$key]['high_url'] = $item['high_url'];
                $new_data[$key]['compressed_url'] = $item['compressed_url'];
            }
        }
        if($new_data){
            self::insert($new_data);
        }
        return true;
    }

}