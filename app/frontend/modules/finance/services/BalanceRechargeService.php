<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/6/14
 * Time: 15:49
 */

namespace app\frontend\modules\finance\services;


use app\frontend\models\Member;
use app\frontend\modules\finance\balance\PreBalanceRecharge;
use app\frontend\modules\finance\balance\RechargePageConfig;

class BalanceRechargeService
{
    public $balanceSet;

    public $rechargeServiceCollect;

    public function __construct()
    {
        $this->balanceSet = \Setting::get('finance.balance');
    }

    public function getConfigs()
    {
        $configs = \app\common\modules\shop\ShopConfig::current()->get('shop-foundation.balance.recharge-page');

        $rechargeServiceCollect = collect();
        foreach ($configs as $configClass) {
            if (class_exists($configClass)) {
                $rechargeServiceCollect->push(new $configClass());
            }

        }

        //权重排序
        return $rechargeServiceCollect->sortByDesc(function ($class) {
            if(method_exists($class,'getOrderBy') && is_callable([$class,'getOrderBy'])) {
                return $class->getOrderBy();
            }
            return 0;
        })->values();
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function getPageConfig()
    {
        if (!isset($this->rechargeServiceCollect)) {
            $this->rechargeServiceCollect = $this->getConfigs();
        }

        return $this->rechargeServiceCollect;
    }

    public function getDisplaySwitch()
    {
        $invoice = 0;
        if (app('plugins')->isEnabled('balance-invoice') && \Setting::get('plugin.balance_invoice')['plugin_switch']) {
            $invoice = 1;
        }

        return [
            'invoice' => $invoice, //余额发票开关
        ];
    }


    public function getPlugin()
    {
        $plugin = $this->getPageConfig()->map(function ($class) {
            if(method_exists($class,'getPlugin') && is_callable([$class,'getPlugin'])) {
                return $class->getPlugin();
            }
            return null;
        })->filter()->collapse()->toArray();

        return $plugin;
    }

    public function getActivity()
    {
        $activity = $this->getPageConfig()->map(function ($class) {
            if(method_exists($class,'getActivity') && is_callable([$class,'getActivity'])) {
                return $class->getActivity();
            }
            return null;
        })->filter()->values()->toArray();

        return $activity;
    }

    /**
     * 余额充值临时支付处理
     * @return PreBalanceRecharge
     * @throws \app\common\exceptions\AppException
     */
    public static function temporary()
    {

        $balanceRecharge = new PreBalanceRecharge();

        $balanceRecharge->init(Member::current(), request());

        $balanceRecharge->generate();

        return $balanceRecharge;
    }
}