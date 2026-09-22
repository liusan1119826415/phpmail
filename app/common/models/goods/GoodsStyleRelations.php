<?php


namespace app\common\models\goods;

use app\common\models\BaseModel;
use app\common\models\goods\GoodsStyle;
class GoodsStyleRelations extends BaseModel
{
    protected $table = 'yz_product_style_relations';

    public $timestamps = true;

    protected $fillable = [
        'goods_id', // 添加此行
        'style_id',
        'type'
    ];
    public function belongsToStyle()
    {
        return $this->belongsTo(GoodsStyle::class,'style_id','id');
    }


}