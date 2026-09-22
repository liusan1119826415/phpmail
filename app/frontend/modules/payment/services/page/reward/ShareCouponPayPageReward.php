<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/7/27
 * Time: 16:29
 */

namespace app\frontend\modules\payment\services\page\reward;



use app\common\models\coupon\ShoppingShareCoupon;

class ShareCouponPayPageReward extends PayPageRewardInterface
{
    public $priority = -1;

    public function whetherHave()
    {
        return $this->getAmount();
    }
    public function _getAmount()
    {
        $share_model = ShoppingShareCoupon::where('order_id', $this->getOrder()->id)->first();

        if ($share_model && $share_model->share_coupon) {
            return count($share_model->share_coupon);
        }

        return 0;
    }
    public function unifyString()
    {
        return "{$this->getAmount()}个{$this->getTitle()}";
    }

    public function afterPayGive()
    {
        return 1;
    }

    public function getTitle()
    {
        return '红包';
    }

    public function getDesc()
    {
        return '快分享给小伙伴';
    }

    public function getIcon()
    {
        return 'red_packet.png';
    }

    public function getUrl()
    {
        return 'couponShare';
    }

    public function getMiniUrl()
    {
        return '/packageD/redPacket/coupon_share';
    }

}