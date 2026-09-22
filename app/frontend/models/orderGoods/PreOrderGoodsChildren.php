<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2017/7/25
 * Time: 下午7:10
 */

namespace app\frontend\models\orderGoods;

use app\common\models\orderGoods\OrderGoodsChildren;
use app\common\modules\orderGoods\models\PreOrderGoods;

class PreOrderGoodsChildren extends OrderGoodsChildren
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    public $orderGoods;

    public function setOrderGoods(PreOrderGoods $orderGoods)
    {
        $this->orderGoods = $orderGoods;
        $this->uid = $this->orderGoods->uid;


        $orderGoods->getOrderGoodsChildrens()->push($this);

    }

}