<?php

namespace app\common\models\project;
use app\common\models\BaseModel;
use app\common\models\Order;

class OrderStage extends BaseModel
{
    protected $table = 'yz_order_stage';

    public $fillable = ["order_id","price","stage"];

    public function order()
    {
        return $this->belongsTo(Order::class,'order_id');
    }

}