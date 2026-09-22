<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/11/7
 * Time: 15:46
 */

namespace app\backend\modules\setting\payment;


class BankTransferPayWidget extends BasePaymentSetWidget
{
    public function defaultData()
    {
        return [
            'remittance' => '',
            'remittance_frontend_img' => '',
            'remittance_bank' => '',
            'remittance_sub_bank' =>'',
            'remittance_bank_account_name' =>'',
            'remittance_bank_account' => '',
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
        return 'bank_transfer';
    }

    public function componentName()
    {
        return 'bankTransfer';
    }

    public function pagePath()
    {
        return $this->getPath('resources/views/setting/pay/js/components/');
    }
}