<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/10/31
 * Time: 15:18
 */

namespace app\frontend\modules\goods\price;


class PointPriceModule extends PriceModuleBase
{
    public function getUnique()
    {
        return 'point';
    }

    public function group()
    {
        return 'goods';
    }

    public function isDisplay()
    {
        return false;
    }

    public function priceFormat()
    {
        return [
            'code' => $this->group(),
            'unique' => $this->getUnique(),
            'value' => $this->goods->price,
            'style' => '',
            'prefix' => '',
            'suffix' => '',
        ];
    }
}