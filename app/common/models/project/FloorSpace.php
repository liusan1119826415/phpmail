<?php

namespace app\common\models\project;
use app\common\models\BaseModel;
use app\common\models\MemberCart;

class FloorSpace extends BaseModel
{
    protected $table = 'yz_project_floor_space';


    public $fillable = ["project_id","floor_id","name","sort"];


    public function spaceCart()
    {
        return $this->hasMany(MemberCart::class,'space_id','id');
    }
}