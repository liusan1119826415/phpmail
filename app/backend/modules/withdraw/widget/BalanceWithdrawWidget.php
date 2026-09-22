<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/10/8
 * Time: 17:01
 */

namespace app\backend\modules\withdraw\widget;


class BalanceWithdrawWidget extends BaseWithdrawSetWidget
{
    /**
     * 返回设置参数
     * @return mixed|array
     */
    public function getData()
    {
        return $this->defaultHandle($this->currentConfig());
    }

    public function defaultData()
    {
        return [
            'status' => 0,
            'poundage_type' => 1,
            'deduct_balance' => 1,
            'deduct_point' => 1,
            'balance_manual_type' => '1',
            'deduct_status' => 0,
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
                    'gong_mall_withdraw'=>app('plugins')->isEnabled('gong-mall-withdraw')&&\Setting::get('plugin.gong-mall-withdraw.is_open'),
                    'integration_pay_share_huifu_withdraw_bank'=>app('plugins')->isEnabled('integration-pay-share')&&\Yunshop\IntegrationPayShare\services\WithdrawSetService::enableWithdraw()
                ],
                'lang' => [
                    'manual_withdrawal' => \Setting::get('shop.lang.zh_cn.income.manual_withdrawal') ?: '手动提现',
                    'tax_withdraw_diy_name' => constant('TAX_WITHDRAW_DIY_NAME') . '-银行卡',
                ]
            ],
        ];
    }

    /**
     * 保存设置数据处理
     * @return mixed|array
     */
    public function fillAttributes()
    {
        return request()->input($this->getWidgetKey(), []);
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
        return $this->getPath('resources/views/withdraw/assets/js/components/');
    }
}
