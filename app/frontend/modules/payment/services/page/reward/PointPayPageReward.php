<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/7/26
 * Time: 15:18
 */

namespace app\frontend\modules\payment\services\page\reward;


use app\common\services\finance\CalculationPointService;

class PointPayPageReward extends PayPageRewardInterface
{
    protected $give_method = false;

    public function whetherHave()
    {
        return $this->getAmount();
    }

    public function afterPayGive()
    {
        if ($this->give_method == 2) {
            return 1;
        }
        return 0;
    }

    public function getTitle()
    {
        $lang =  \app\common\services\LangService::getLangSet('member_center');

        return $lang['credit1']?:'积分';
    }

    public function getDesc()
    {
        if ($this->give_method === false) {
            return '';
        }

        switch ($this->give_method) {
            case 1:
                $desc = '每月一号赠送';
                break;
            case 2:
                $desc = '订单支付后赠送';
                break;
            default:
                $desc = '订单完成后赠送';
        }

        return $desc;
    }

    public function _getAmount()
    {
        $hasManyOrderGoods = $this->getOrder()->orderGoods;

        $total_amount = 0;
        $point_type_array = [];
        foreach ($hasManyOrderGoods as $orderGoods) {

            // 商品营销数据
            $goodsSale = $orderGoods->hasOneGoods->hasOneSale;

            $point_data = CalculationPointService::calculationPointByGoods($orderGoods);

            if ($point_data['point']) {

                $point_type_array[] = is_null($goodsSale) ? 0 : $goodsSale->point_type;

                $total_amount += $point_data['point'];
            }
        }

        $point_type = array_unique($point_type_array);
        if ($point_type && count($point_type) == 1) {
            $this->give_method = array_first($point_type);

        }

        return bankerRounding($total_amount);
    }

    public function getIcon()
    {
        return 'integral.png';
    }

    public function getUrl()
    {
        return 'integral_v2';
    }

    public function getMiniUrl()
    {
        return '/packageB/member/integral/integral';
    }

}