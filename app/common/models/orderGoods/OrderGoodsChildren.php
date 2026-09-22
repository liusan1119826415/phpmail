<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2017/7/25
 * Time: 下午7:10
 */

namespace app\common\models\orderGoods;

use app\common\models\BaseModel;

/**
 * Class OrderGoodsTaxFee
 * @package app\common\models\orderGoods
 * @property int id
 * @property int uid
 * @property float amount
 * @property string name
 * @property string fee_code
 * @property int order_goods_id
 */
class OrderGoodsChildren extends BaseModel
{
    public $table = 'yz_order_goods';
    protected $fillable = [];
    protected $guarded = ['id'];
}