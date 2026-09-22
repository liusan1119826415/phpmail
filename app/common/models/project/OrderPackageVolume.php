<?php

namespace app\common\models\project;
use app\common\models\BaseModel;
use app\common\models\MemberCart;
class OrderPackageVolume extends BaseModel
{
    protected $table = 'yz_order_package_volume';

    public $fillable = ["order_id","express_sn","package_num","volume"];

}