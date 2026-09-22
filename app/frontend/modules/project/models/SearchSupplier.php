<?php


namespace app\frontend\modules\project\models;


use Yunshop\Supplier\common\models\Supplier;
use Yunshop\Supplier\common\models\SupplierBrand;

class SearchSupplier extends Supplier
{



    public function hasManyBrand()
    {
        return $this->hasMany(SupplierBrand::class,'supplier_id','id');
    }
}