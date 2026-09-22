<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/11/7
 * Time: 15:45
 */

namespace app\backend\modules\setting\payment;


class PayBehalfPayWidget extends BasePaymentSetWidget
{
    public function defaultData()
    {
        return [
            'another' => '0',
            'another_share_title' => '0',
            'another_share_type' =>1,
        ];
    }

    public function currentConfig()
    {
        $pay = \Setting::get('shop.pay');

        if (empty($pay)) {
            return [];
        }

        return array_intersect_key($pay, $this->defaultData());
    }

    public function getData()
    {
        return array_merge($this->defaultData(), $this->currentConfig());
    }

    public function fillAttributes()
    {
        return $this->getSubmitParam();
    }

    /**
     * 设置保存键
     * @return mixed|string
     */
    public function getWidgetKey()
    {
        return 'pay_behalf';
    }

    public function componentName()
    {
        return 'payBehalf';
    }

    public function pagePath()
    {
        return $this->getPath('resources/views/setting/pay/js/components/');
    }
}