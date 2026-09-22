<?php


namespace app\common\models\goods;

use app\common\models\BaseModel;
class GoodsStyle extends BaseModel
{
    protected $table = 'yz_product_styles';

    public $timestamps = true;

    public function goodsStyleRelations()
    {
        return $this->hasMany(GoodsStyleRelations::class,'style_id','id');
    }

}