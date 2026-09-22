<?php

namespace app\common\models\project;
use app\common\models\BaseModel;
use app\common\models\MemberCart;
use app\common\models\project\Floors;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlanImg extends BaseModel
{

   // use SoftDeletes;
    protected $table = 'yz_project_plan_img';

    public $fillable = ["project_id","floor_id","thumb","dxf_url","goods_total","original_file"];

    public function floors()
    {
        return $this->belongsTo(Floors::class,'floor_id');
    }

}