<?php

namespace app\common\models\project;
use app\common\models\Address;
use app\common\models\BaseModel;
use app\common\models\MemberCart;
use app\common\models\project\Floors;
use Illuminate\Database\Eloquent\SoftDeletes;

class InstallOrder extends BaseModel
{

   // use SoftDeletes;
    protected $table = 'yz_install_order';

    public $timestamps = true;

    protected $fillable = ["order_id","install_days","floor","has_elevator","remark","install_publish_time"];



}