<?php

namespace app\common\models\project;
use app\common\models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class CloudDesign extends BaseModel
{

    use SoftDeletes;
    protected $table = 'yz_cloud_design';

    protected $casts = [
        'design_data' => 'json',
    ];

    public $guarded=[];

}