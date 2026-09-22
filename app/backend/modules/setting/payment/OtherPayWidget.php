<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/11/7
 * Time: 15:33
 */

namespace app\backend\modules\setting\payment;


class OtherPayWidget extends BasePaymentSetWidget
{
    public $priority = -999;

    public function defaultData()
    {
        return [
            'ios_virtual_pay' => '0',
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
        return 'other';
    }

    public function componentName()
    {
        return 'other';
    }

    public function pagePath()
    {
        return $this->getPath('resources/views/setting/pay/js/components/');
    }
}