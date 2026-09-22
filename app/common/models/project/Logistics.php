<?php

namespace app\common\models\project;
use app\common\models\Address;
use app\common\models\BaseModel;
use app\common\models\MemberCart;
use app\common\models\project\Floors;
use Illuminate\Database\Eloquent\SoftDeletes;

class Logistics extends BaseModel
{

   // use SoftDeletes;
    protected $table = 'yz_logistics_express';

    public $fillable = ["province_id","freight","status"];

    public function province()
    {
        return $this->belongsTo(Address::class,'province_id');
    }
}