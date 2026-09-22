<?php

namespace app\common\models\refund;


use app\common\models\BaseModel;
use app\common\models\Order;
use app\common\models\OrderGoods;
use app\framework\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class RefundGoodsLog
 * @package app\common\models\refund
 */
class RefundOrderGoods extends BaseModel
{

    public $table = 'yz_order_refund_goods';





}
