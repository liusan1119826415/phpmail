<?php

namespace app\frontend\modules\withdraw\services;

use app\common\facades\Setting;
use app\frontend\modules\withdraw\services\withdrawButton\BaseWithdrawButton;

class WithdrawButtonManager
{
    private $data;

    public function __construct()
    {
        $plugins = app('plugins')->getEnabledPlugins();
        $config = [
            'manual'            => \app\frontend\modules\withdraw\services\withdrawButton\Manual::class,
            'balance'           => \app\frontend\modules\withdraw\services\withdrawButton\Balance::class,
            'wechat'            => \app\frontend\modules\withdraw\services\withdrawButton\Wechat::class,
            'alipay'            => \app\frontend\modules\withdraw\services\withdrawButton\Alipay::class,
            'eup_pay'           => \app\frontend\modules\withdraw\services\withdrawButton\EupPay::class,
            'converge_pay'      => \app\frontend\modules\withdraw\services\withdrawButton\ConvergePay::class,
            'high_light_wechat' => \app\frontend\modules\withdraw\services\withdrawButton\HighLightWechat::class,
            'high_light_bank'   => \app\frontend\modules\withdraw\services\withdrawButton\HighLightBank::class,
            'high_light_alipay' => \app\frontend\modules\withdraw\services\withdrawButton\HighLightAlipay::class,
            'worker_withdraw_wechat' => \app\frontend\modules\withdraw\services\withdrawButton\WorkerWithdrawWechat::class,
            'worker_withdraw_bank'   => \app\frontend\modules\withdraw\services\withdrawButton\WorkerWithdrawBank::class,
            'worker_withdraw_alipay' => \app\frontend\modules\withdraw\services\withdrawButton\WorkerWithdrawAlipay::class,
            'eplus_withdraw_bank'    => \app\frontend\modules\withdraw\services\withdrawButton\EplusWithdrawBank::class,
            'silver_point'           => \app\frontend\modules\withdraw\services\withdrawButton\SliverPoint::class,
            'jianzhimao_bank'        => \app\frontend\modules\withdraw\services\withdrawButton\JianzhimaoBank::class,
            'tax_withdraw_bank'      => \app\frontend\modules\withdraw\services\withdrawButton\TaxWithdrawBank::class,
//            'consol_withdraw_alipay' => \app\frontend\modules\withdraw\services\withdrawButton\ConsolWithdrawAlipay::class,
            'consol_withdraw_bank'   => \app\frontend\modules\withdraw\services\withdrawButton\ConsolWithdrawBank::class,
//            'consol_withdraw_wechat' => \app\frontend\modules\withdraw\services\withdrawButton\ConsolWithdrawWechat::class,
            'huiis_ali'              => \app\frontend\modules\withdraw\services\withdrawButton\HuiisAlipay::class,
            'huiis_bank'             => \app\frontend\modules\withdraw\services\withdrawButton\HuiisBank::class,
            'huiis_wx'               => \app\frontend\modules\withdraw\services\withdrawButton\HuiisWechat::class,
        ];
        foreach ($plugins as $plugin) {
            if (method_exists($plugin->app(), 'getAssetConfig')) {
                foreach ($plugin->app()->getWithdrawButton() as $key => $class) {
                    $config[$key] = $class;
                }
            }
        }
        $this->data = $config;
    }

    /**
     * 收入类型是否开启自定义提现方式
     * @param string $incomeType
     * @return bool
     */
    private function incomeCustomStatus(string $incomeType): bool
    {
        return !!Setting::get("withdraw.{$incomeType}.withdraw_type");
    }

    private function getIncomeSet(string $incomeType)
    {
        if ($incomeType && $incomeType <> 'default' && $this->incomeCustomStatus($incomeType)) {
            //StoreCashier、StoreWithdraw、StoreBossWithdraw、HotelCashier、hotel_withdraw':
            return Setting::get("withdraw.{$incomeType}.withdraw_method") ? : [];
        } else {
            $return = [];
            $set = Setting::get('withdraw.income');
            foreach ($set as $key => $item) {
                if ($item) {
                    $return[] = $key;
                }
            }
            return $return;
        }
    }

    /**
     * @param string $incomeType
     * @return array
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function getIncomeWithdrawButton(string $incomeType): array
    {
        $config = [];
        foreach ($this->getIncomeSet($incomeType) as $item) {

            if ($this->data[$item] && class_exists($this->data[$item])) {
                $config[] = app()->make($this->data[$item]);
            }
        }
        $configList = collect($config);
        $configList = $configList->filter(function (BaseWithdrawButton $class) {
            return $class->isEnabled();
        });
        $buttonList = $configList->map(function (BaseWithdrawButton $button) {
            return array_merge([
                'name'   		=> $button->getName(),
                'other_name' 	=> $button->getOtherName(),
                'value'   		=> $button->getValue(),
                'extra_data'    => $button->getExtraData(),
                'tips'    	    => $button->getTips(),
                'icon'          => $button->getIcon(),
                'title'         => $button->getTitle(),
            ],$button->getOtherData());
        });
        return $buttonList->sortByDesc('weight')->values()->all();
    }
}