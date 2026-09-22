<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/6/19
 * Time: 10:29
 */

namespace app\frontend\modules\finance\balance;


class RechargePageConfig
{
    public $balanceSet;

    public function __construct()
    {
        $this->balanceSet = \Setting::get('finance.balance');
    }

    public function getOrderBy()
    {
        return 0;
    }

    /**
     * @return array
     */
    public function getPlugin()
    {
        return [];
    }

    public function getActivity()
    {

        $activity = $this->shopActivity();
        if (!array_filter($activity['value'])) {
            return [];
        }

        return $activity;
    }

    protected function shopActivity()
    {

        $result = [
            'name' => '充值活动',
            'code' => 'shop',
            'desc' => [],
            'value_type' => 'gift',
            'value' => [$this->shopRewardPoint()],
        ];

        if (!$this->balanceSet['recharge_activity'] || empty($this->balanceSet['sale'])) {
            return $result;
        }
        $activity_start = date('Y-m-d H:i:s', $this->balanceSet['recharge_activity_start']?:0);

        $activity_end =  date('Y-m-d H:i:s', $this->balanceSet['recharge_activity_end']?:0);

        if (empty($this->balanceSet['recharge_activity_fetter']) || $this->balanceSet['recharge_activity_fetter'] == -1) {
            $fetter = '';
        } else {
            $fetter = $this->balanceSet['recharge_activity_fetter'];
        }

        $unit = $this->balanceSet['proportion_status'] ? '%' : '元';

        $sale = array_map(function ($item) use ($unit) {
            return "充值满￥{$item['enough']}，赠送{$item['give']}{$unit}";
        },$this->balanceSet['sale']);

        $result['desc'] =  array_filter([
            "活动时间：{$activity_start}--{$activity_end}",
            $fetter?"参与次数：每人最多参与{$fetter}次":'',
        ]);

        $result['value'] = array_merge($result['value'],$sale);


        return $result;
    }

    protected function shopRewardPoint()
    {
        $shop_set = \Setting::get('shop');

        if ($this->balanceSet['charge_reward_swich'] == 1) {

            $pointName = $shop_set['credit1'] ? $shop_set['credit1'] : '积分';

            $rate = ($this->balanceSet['charge_reward_rate'] ?: 100) / 100;

            return "充值赠送{$pointName}, 比例：1余额等于{$rate}{$pointName}";
        }

        return '';
    }
}