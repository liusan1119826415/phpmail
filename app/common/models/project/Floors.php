<?php

namespace app\common\models\project;
use app\common\models\BaseModel;
use app\common\models\MemberCart;
class Floors extends BaseModel
{
    protected $table = 'yz_project_floors';

    public $fillable = ["project_id","name","sort",'is_cad','thumb','goods_total'];


    public function spaces()
    {
        return $this->hasMany(FloorSpace::class,"floor_id","id");
    }

    public function spacesActivate()
    {
        return $this->hasOne(FloorSpace::class,"floor_id","id")->where('activate',1);
    }

    public function floorCart()
    {
        return $this->hasMany(MemberCart::class,'floor_id','id');
    }
}