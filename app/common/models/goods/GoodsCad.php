<?php
/**
 * Author:
 * Date: 2017/7/13
 * Time: 下午3:10
 */

namespace app\common\models\goods;


use app\common\models\BaseModel;

class GoodsCad extends BaseModel
{
    public $table = 'yz_goods_cad';


    public $timestamps = false;

    protected $guarded = [''];




    public function scopeOfGoodsId($query,$goodsId)
    {
        return $query->where('goods_id',$goodsId);
    }




}