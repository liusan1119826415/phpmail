<?php


namespace app\frontend\modules\project\models;



use Yunshop\Supplier\common\models\Supplier;
use app\common\models\goods\GoodsStyleRelations;
class SearchGoods extends \app\frontend\modules\goods\models\Goods
{

    /**
     * @name 关联供应商表
     * @author yangyang
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function beLongsToSupplier()
    {
        return $this->belongsTo(Supplier::class, 'supp_id', 'id');
    }

    ##关联商品风格
    public function hasManyGoodsStyle()
    {
        return $this->hasMany(GoodsStyleRelations::class,'goods_id','id')->where('type',1);
    }

    ##关联工艺材质
    public function hasManyCraftMaterials()
    {
        return $this->hasMany(GoodsStyleRelations::class,'goods_id','id')->where('type',2);
    }

}
