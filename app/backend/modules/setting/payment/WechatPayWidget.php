<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/11/1
 * Time: 17:40
 */

namespace app\backend\modules\setting\payment;


use app\common\exceptions\AppException;
use app\common\models\AccountWechats;
use app\common\models\BaseModel;
use app\common\services\Utils;

class WechatPayWidget extends BasePaymentSetWidget
{
    protected $filter_keys = [
        'weixin_apisecret','weixin_cert', 'weixin_key','new_weixin_cert','new_weixin_key','rsa_private_key','rsa_public_key',
        'alipay_transfer_private','alipay_app_public_cert','alipay_public_cert','alipay_root_cert'
    ];

    public function currentConfig()
    {
        $pay = \Setting::get('shop.pay');

        $account = AccountWechats::getAccountByUniacid(\YunShop::app()->uniacid);
        if (empty($pay['weixin_appid']) && empty($pay['weixin_secret']) && !empty($account)) {
            $pay['weixin_appid'] = $account->key;
            $pay['weixin_secret'] = $account->secret;
        }

        if (empty($pay)) {
            return [];
        }

        return array_intersect_key($pay, $this->defaultData());
    }


    /**
     * 返回设置参数
     * @return mixed|array
     */
    public function getData()
    {
        return array_merge($this->defaultData(),$this->currentConfig());
    }

    public function defaultData()
    {
        return [
            'weixin' => "0",
            'weixin_pay' => "0",
            'wechat_h5' => "0",
            'wechat_micro' => "0",
            'wechat_native' => "0",
            'weixin_appid' => '',
            'weixin_secret' => '',
            'weixin_mchid' => '',
            'weixin_apisecret' => '',
            'weixin_version' => "0",
            'weixin_cert' => '',
            'weixin_key' => '',
            'new_weixin_cert' => '',
            'new_weixin_key' => '',
            'weixin_apiv3' => "0",
            'weixin_apiv3_secret' => '',
        ];
    }

    /**
     * 保存设置数据处理
     * @return mixed|array
     */
    public function fillAttributes()
    {
        $requestModel =$this->getSubmitParam();
        if (isset($requestModel['weixin_version']) && $requestModel['weixin_version'] == 1) {
            if (!empty($requestModel['new_weixin_cert']) && !empty($requestModel['new_weixin_key'])) {
                $updatefile_v2 = $this->updateFileV2(['weixin_cert' => $requestModel['new_weixin_cert'], 'weixin_key' => $requestModel['new_weixin_key']]);

                if ($updatefile_v2['status'] == 0) {
                    throw new AppException('文件保存失败');
                }

                $requestModel = array_merge($requestModel, $updatefile_v2['data']);
            }
        }

        return $requestModel;
    }

    /**
     * 保存修改日志
     */
    protected function updRecord()
    {
        $pay = \Setting::get('shop.pay')?:[];

        $settingLog = new \app\common\services\operation\SettingLog('shop.pay');

        $settingLog->setBeforeValue($pay,$this->filter_keys);
        $settingLog->setAfterValue($this->getAttributes(),$this->filter_keys);
        $settingLog->saveOperation();
    }

    //支付密钥文本转文件
    private function updateFileV2($file_data)
    {
        $data = [];
        $uniacid = \YunShop::app()->uniacid;
        $file_suffix = '.pem';
        foreach ($file_data as $key => $value) {
            $file_name = $uniacid . "_" . $key . $file_suffix;
            $bool = \Storage::disk('cert')->put($file_name, $value);

            if ($bool) {
                $data[$key] = storage_path('cert/' . $file_name);
            } else {
                return ['status' => 0];
            }

        }

        if (!empty($data)) {
            return ['status' => 1, 'data' => $data];
        }

        return null;
    }

    /**
     * 设置保存键
     * @return mixed|string
     */
    public function getWidgetKey()
    {
        return 'wechat';
    }

    public function componentName()
    {
        return 'wechat';
    }

    public function pagePath()
    {
        return $this->getPath('resources/views/setting/pay/js/components/');
    }
}