<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/10/31
 * Time: 14:57
 */

namespace app\frontend\modules\goods\price;


class DefaultPriceModule extends PriceModuleBase
{
    public function getUnique()
    {

       return 'default';
    }

    public function group()
    {
        return 'goods';
    }

    public function isDisplay()
    {
        return true;
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