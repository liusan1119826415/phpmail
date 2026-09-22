<?php


namespace app\frontend\modules\project\models;


use Yunshop\Supplier\common\models\Supplier;
use Yunshop\Supplier\common\models\SupplierFollow;

class Follow extends SupplierFollow
{

    public static function IsFollow($member_id,$supplier_id)
    {
        return self::where('member_id',$member_id)->where('supplier_id',$supplier_id)->first();
    }


    public static function getMyFollow($member_id)
    {
       return self::where('member_id',$member_id)->with(['belongsToSupplier'=>function($query){
           $query->select("id","store_name","logo","introduction", "province_id", "city_id")->where('status',1);
       }]);
    }


    public function belongsToSupplier()
    {
        return $this->belongsTo(Supplier::class,'supplier_id','id');
    }


}