<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/11/8
 * Time: 16:39
 */

namespace app\backend\modules\setting\payment;

use app\common\facades\Setting;
use app\common\helpers\Url;
use app\common\services\payment\IntegrationPay;

class IntegrationPayWidget extends BasePaymentSetWidget
{
    public function usable()
    {
        return true;
    }

    public function defaultData()
    {
        return [
            'integration_pay' => '0',
            'wechat_pay' => '0',
            'alipay_pay' =>'0',
            'lkl_cashier' => '0',
        ];
    }

    public function currentConfig()
    {
      return Setting::get('payment.integration_pay')?:[];
    }

    public function getData()
    {
        $paySet = array_merge($this->defaultData(), $this->currentConfig());

        if (!isset($paySet['account_info'])) {
            $paySet['account_info'] = [];
        }

//        $paySet['account_info'] = [];
//        if ($this->currentConfig()) {
//            $result = IntegrationPay::getConfig();
//
//            if ($result && $result['status']) {
//                $paySet['account_info'] = $result['data'];
//            }
//        }


        return [
            'pay_set' => $paySet,
            'plugin_integration_share' => app('plugins')->isEnabled('integration-pay-share'),
            'service_provider_connect' => app('plugins')->isEnabled('service-provider-connect'),
        ];
    }

    public function fillAttributes()
    {
        return $this->getSubmitParam();
    }

    /**
     * 保存修改日志
     */
    protected function updRecord()
    {
        $pay = Setting::get('payment.integration_pay')?:[];

        $settingLog = new \app\common\services\operation\SettingLog('payment.integration_pay');

        $settingLog->setBeforeValue($pay);
        $settingLog->setAfterValue($this->getAttributes());
        $settingLog->saveOperation();
    }

    public function saveOperation()
    {
        Setting::set('payment.integration_pay', $this->getAttributes());
    }

    /**
     * 设置保存键
     * @return mixed|string
     */
    public function getWidgetKey()
    {
        return 'integration_pay';
    }

    public function componentName()
    {
        return 'integrationPay';
    }

    public function pagePath()
    {
        return $this->getPath('resources/views/setting/pay/js/components/');
    }
}