<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2018/5/23
 * Time: 下午3:55
 */

namespace app\frontend\modules\order\discount;

use app\common\facades\Setting;
use app\common\models\MemberShopInfo;

class EnoughReduce extends BaseDiscount
{
    protected $code = 'enoughReduce';
    protected $name = '全场满减优惠';

    /**
     * 获取总金额
     * @return int|mixed
     * @throws \app\common\exceptions\AppException
     */
    protected function _getAmount()
    {

        if(!Setting::get('enoughReduce.open')){
            return 0;
        }

        //只有商城、益生插件、应用市场、应用市场-子平台
        if(!in_array($this->order->plugin_id,[0,61,57,59])){
            return 0;
        }

        $yzMember = MemberShopInfo::select('level_id')->where('member_id',$this->order->uid)->first();
        $allSetting = Setting::getByGroup('enoughReduce');
        if ($allSetting['open_level_enough_reduce'] && $yzMember->level_id) {
            //开启了等级满减&会员有等级
            foreach ($allSetting['level_enough_reduce'] as $level_enough_reduce) {
                if ($level_enough_reduce['level_id'] && $level_enough_reduce['level_id'] == $yzMember->level_id) {
                    $enough_reduces = collect(($level_enough_reduce['enough_reduce']?:[]))->sortByDesc(function ($item) {
                        return $item['enough'];
                    });

                    foreach ($enough_reduces as $enough_reduce) {
                        if ($this->order->getPriceBefore($this->getCode()) >= $enough_reduce['enough']) {
                            return min($enough_reduce['reduce'],$this->order->getPriceBefore($this->getCode()));
                        }
                    }
                }
            }
            return 0;
        }

        // 获取满减设置,按enough倒序
        $settings = collect(Setting::get('enoughReduce.enoughReduce'));

        if (empty($settings)) {
            return 0;
        }

        $settings = $settings->sortByDesc(function ($setting) {
            return $setting['enough'];
        });

        // 订单总价满足金额,则返回优惠金额
        foreach ($settings as $setting) {

            if ($this->order->getPriceBefore($this->getCode()) >= $setting['enough']) {
                return min($setting['reduce'],$this->order->getPriceBefore($this->getCode()));
            }
        }
        return 0;
    }
}