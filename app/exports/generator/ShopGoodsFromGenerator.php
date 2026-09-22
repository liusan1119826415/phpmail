<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2021/10/29
 * Time: 16:47
 */

namespace app\exports\generator;


class ShopGoodsFromGenerator extends MergeFromGenerator
{
    public $second = ['option', 'stock', 'price', 'market_price', 'cost_price', 'goods_sn', 'product_sn'];
    public $third = [];
}
