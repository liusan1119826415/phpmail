<?php
/**
 * Author:
 * Date: 2017/7/13
 * Time: 下午3:10
 */

namespace app\common\models\goods;


use app\common\models\BaseModel;

class GoodsSearchSort extends BaseModel
{
    public $table = 'yz_goods_search_sort';

    public $fillable = ["goods_id","distance"];
    public $timestamps = false;



}