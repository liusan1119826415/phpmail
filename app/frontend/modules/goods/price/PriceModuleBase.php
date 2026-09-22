<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/10/31
 * Time: 14:51
 */

namespace app\frontend\modules\goods\price;


use app\common\models\Goods;

abstract class PriceModuleBase
{
    public $goods;

    public $priority;

    //组说名
    public $baseGroups = [
        'goods' => '商品价格',
        'member_price' => '会员价',
    ];

    public function __construct(Goods $goods, $priority = 0)
    {
        $this->goods = $goods;

        $this->priority = $priority;
    }

    public function getPriority()
    {
        return $this->priority;
    }

    abstract public function group();

    abstract public function getUnique();

    abstract public function isDisplay();

    abstract public function priceFormat();
}