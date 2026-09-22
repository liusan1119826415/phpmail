<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/7/26
 * Time: 11:39
 */

namespace app\frontend\modules\payment\services\page\reward;


class BalancePayPageReward extends PayPageRewardInterface
{
    public function whetherHave()
    {
        return $this->getAmount();
    }

    public function afterPayGive()
    {
        return 0;
    }

    public function getTitle()
    {
        $lang =  \app\common\services\LangService::getLangSet('member_center');

        return $lang['credit']?:'余额';
    }

    public function getDesc()
    {
        return '订单完成后赠送';
    }

    public function getIcon()
    {
        return 'balance.png';
    }

    public function getUrl()
    {
       return 'storeManage';
    }

    public function getMiniUrl()
    {
       return '/packageA/member/balance/balance/balance';
    }

    public function _getAmount()
    {
        $orderGoods = $this->getOrder()->orderGoods;
        $change_value = 0;
        foreach ($orderGoods as $goodsModel) {
            $goodsSaleModel = $goodsModel->hasOneGoods->hasOneSale;

            if (!$goodsSaleModel || empty($goodsSaleModel->award_balance)) {
                continue;
            }
            $change_value += $this->proportionMath($goodsModel->payment_amount, $goodsSaleModel->award_balance, $goodsModel->total);
        }

        return bankerRounding($change_value);
    }

    private function proportionMath($price, $proportion, $total)
    {
        if (strexists($proportion, '%')) {
            $proportion = str_replace('%', '', $proportion);
            return bcdiv(bcmul($price,$proportion,4),100,2);
        }
        return bcmul($proportion,$total,2);
    }
}