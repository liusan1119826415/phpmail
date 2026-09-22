<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/10/8
 * Time: 18:13
 */

namespace app\backend\modules\withdraw\widget;


use app\backend\modules\withdraw\models\WithdrawRichText;

class IncomeWithdrawWidget extends BaseWithdrawSetWidget
{
    /**
     * 返回设置参数
     * @return mixed|array
     */
    public function getData()
    {
        $data = $this->defaultHandle($this->currentConfig());
        return $data;
    }

    /**
     * 保存设置数据处理
     * @return mixed|array
     */
    public function fillAttributes()
    {
        $data = request()->input('income', []);

        if (count($data['show_withdraw_date']) === 31 || empty($data['show_withdraw_date'])) {
            $data['withdraw_date'] = "0";
        } elseif (!empty($data['show_withdraw_date'])) {
            $data['withdraw_date'] = [];
            foreach ($data['show_withdraw_date'] as $date) {
                $date += 1;
                $data['withdraw_date'][$date] = $date;
            }
        }

        WithdrawRichText::createOrUpdate($data['withdraw_rich_text']);
        unset($data['withdraw_rich_text']);

        return $data;
    }

    public function defaultData()
    {
        return [
            'balance' => '0',
            'balance_special' => '0',
            'wechat' => '0',
            'alipay' => '0',
            'wechat_api_v3' => '0',
            'special_poundage_type' => '1',
            'huanxun' => '0',
            'eup_pay' => '0',
            'converge_pay' => '0',
            'yop_pay' => '0',
            'yee_pay' => '0',
            'high_light_wechat' => '0',
            'high_light_alipay' => '0',
            'high_light_bank' => '0',
            'manual' => '0',
            'service_switch' => '1',
            'service_tax_calculation' => '0',
            'manual_type' => '1',
            'servicetax' => [],
            'show_withdraw_date' => [],
            'withdraw_rich_text' => WithdrawRichText::uniacid()->value('content'),
            'common' => [
                'plugin_open' => [
                    'cloud_pay_money' => app('plugins')->isEnabled('cloud-pay-money') && \Setting::get('plugin.cloud-pay-money.is_open'),
                    'huanxun' => app('plugins')->isEnabled('huanxun'),
                    'converge_pay' => app('plugins')->isEnabled('converge_pay'),
                    'eup_pay' => app('plugins')->isEnabled('eup-pay'),
                    'high_light' => app('plugins')->isEnabled('high-light') && \Yunshop\HighLight\services\SetService::getStatus(),
                    'eplus_pay' => app('plugins')->isEnabled('eplus-pay') && \Yunshop\EplusPay\services\SettingService::usable(),
                    'worker_withdraw_wechat' => \app\common\services\finance\IncomeService::workerWithdrawEnable(2),
                    'worker_withdraw_alipay' => \app\common\services\finance\IncomeService::workerWithdrawEnable(1),
                    'silver_point_pay' => app('plugins')->isEnabled('silver-point-pay'),
                    'jianzhimao_withdraw' => app('plugins')->isEnabled('jianzhimao-withdraw'),
                    'tax_withdraw' => app('plugins')->isEnabled('tax-withdraw'),
                    'consol_withdraw' => app('plugins')->isEnabled('consol-withdraw'),
                    'yop_pay' => app('plugins')->isEnabled('yop-pay'),
                    'yee_pay' => app('plugins')->isEnabled('yee-pay'),
                    'gong_mall_withdraw'=>app('plugins')->isEnabled('gong-mall-withdraw')&&\Setting::get('plugin.gong-mall-withdraw.is_open'),
                    'integration_pay_share_huifu_withdraw_bank'=>app('plugins')->isEnabled('integration-pay-share')&&\Yunshop\IntegrationPayShare\services\WithdrawSetService::enableWithdraw(),
                    'hema_withdraw_bank'=>app('plugins')->isEnabled('hema-pay'),
                    'renlijia_withdraw'=>app('plugins')->isEnabled('renlijia-withdraw')&&\Setting::get('plugin.renlijia-withdraw.is_open'),
                ],
                'lang' => [
                    'manual_withdrawal' => \Setting::get('shop.lang.zh_cn.income.manual_withdrawal') ?: '手动提现',
                    'tax_withdraw_diy_name' => constant('TAX_WITHDRAW_DIY_NAME') . '-银行卡',
                    'renlijia_withdraw_diy_name'=>app('plugins')->isEnabled('renlijia-withdraw')?\Yunshop\RenLiJiaWithdraw\common\services\CommonService::getPluginName():'人力家'
                ]
            ],
        ];
    }

    /**
     * 设置保存键
     * @return mixed|string
     */
    public function getWidgetKey()
    {
        return 'income';
    }

    public function componentName()
    {
        return 'income_withdrawal_basic_set';
    }

    public function pagePath()
    {
        return $this->getPath('resources/views/withdraw/assets/js/components/');
    }
}
