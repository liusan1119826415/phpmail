<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/12/11
 * Time: 10:05
 */

namespace app\backend\modules\setting\payment;


class BalanceWidget extends BasePaymentSetWidget
{
    public function defaultData()
    {
        return [
            'credit' => '0',
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
        return 'balance';
    }

    public function componentName()
    {
        return 'balance';
    }

    public function pagePath()
    {
        return $this->getPath('resources/views/setting/pay/js/components/');
    }
}