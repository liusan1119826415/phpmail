<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/10/31
 * Time: 15:01
 */

namespace app\frontend\modules\goods\price;


class VipPriceModule extends PriceModuleBase
{
    public function getUnique()
    {
        return 'shop_member_price';
    }

    public function group()
    {
        return 'member_price';
    }

    public function isDisplay()
    {
        if (app('plugins')->isEnabled('member-price')
            || \Setting::get('plugin.member-price.is_open_micro') == 1) {
            return true;
        }
        return false;
    }

    public function priceFormat()
    {

        if ($this->goods->price_level == 2) {
            $price = $this->goods->vip_next_price;
        } else {
            $price = $this->goods->vip_price;
        }

        return [
            'code' => $this->group(),
            'unique' => $this->getUnique(),
            'value' => $price,
            'style' => '',
            'prefix' => '',
            'suffix' => '',
        ];
    }
}