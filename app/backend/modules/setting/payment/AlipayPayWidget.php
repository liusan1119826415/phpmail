<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/11/2
 * Time: 11:30
 */

namespace app\backend\modules\setting\payment;


use app\common\helpers\Url;
use app\common\services\Utils;
use app\common\facades\Setting;

class AlipayPayWidget extends BasePaymentSetWidget
{

     protected $filter_keys = [
         'weixin_apisecret','weixin_cert', 'weixin_key','new_weixin_cert','new_weixin_key','rsa_private_key','rsa_public_key',
         'alipay_transfer_private','alipay_app_public_cert','alipay_public_cert','alipay_root_cert'
     ];

    public function currentConfig()
    {
        $pay = Setting::get('shop.pay');


        if (isset($pay['secret']) && 1 == $pay['secret']) {
            try {
                Utils::dataDecrypt($pay);
            } catch (\Exception $e) {
                \Log::debug('------error msg------', [$e->getMessage()]);
            }
        }

        if (empty($pay)) {
            return [];
        }

        return array_intersect_key($pay, $this->defaultData());
    }


    public function getData()
    {
        return array_merge($this->defaultData(),$this->currentConfig());
    }

    public function defaultData()
    {
        return [
            'secret' => 1, //todo 这个不知道干嘛的，之前的页面默认就是1也没有地方能修改。那就是一直都是1有什么意义？？？
            'alipay' => '0',
            'alipay_app_id' => '',
            'rsa_private_key' => '',
            'rsa_public_key' => '',
            'alipay_withdrawals' => '0',
            'alipay_transfer_app_id' => '',
            'alipay_transfer_private' => '',
            'alipay_app_public_cert' => '',
            'alipay_public_cert' => '',
            'alipay_root_cert' => '',
        ];
    }

    /**
     * 保存设置数据处理
     * @return mixed|array
     */
    public function fillAttributes()
    {
        $requestModel = $this->getSubmitParam();

        if (isset($requestModel['secret']) && 1 == $requestModel['secret']) {
            Utils::dataEncrypt($requestModel);
        }

        return $requestModel;

    }

    /**
     * 保存修改日志
     */
    protected function updRecord()
    {
        $pay = Setting::get('shop.pay')?:[];

        $settingLog = new \app\common\services\operation\SettingLog('shop.pay');

        $settingLog->setBeforeValue($pay,$this->filter_keys);
        $settingLog->setAfterValue($this->getAttributes(),$this->filter_keys);
        $settingLog->saveOperation();
    }

    //这样的配置应该没有用了，原因商城已经没有支付宝旧版支付了
    private function setAlipayParams($data)
    {
        Setting::set('alipay.pem', storage_path() . '/cert/cacert.pem');
        Setting::set('alipay.partner_id', $data['alipay_partner']);
        Setting::set('alipay.seller_id', $data['alipay_account']);
        Setting::set('alipay-mobile.sign_type', 'RSA');
        Setting::set('alipay-mobile.private_key_path', storage_path() . '/cert/private_key.pem');
        Setting::set('alipay-mobile.public_key_path', storage_path() . '/cert/public_key.pem');
        Setting::set('alipay-mobile.notify_url', Url::shopSchemeUrl('payment/alipay/notifyUrl.php'));
        Setting::set('alipay-web.key', $data['alipay_secret']);
        Setting::set('alipay-web.sign_type', 'MD5');
        Setting::set('alipay-web.notify_url', Url::shopSchemeUrl('payment/alipay/notifyUrl.php'));
        Setting::set('alipay-web.return_url', Url::shopSchemeUrl('payment/alipay/returnUrl.php'));
    }

    /**
     * 设置保存键
     * @return mixed|string
     */
    public function getWidgetKey()
    {
        return 'alipay';
    }

    public function componentName()
    {
        return 'alipay';
    }

    public function pagePath()
    {
        return $this->getPath('resources/views/setting/pay/js/components/');
    }
}