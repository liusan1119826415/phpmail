<?php
/****************************************************************
 * Author:  king -- LiBaoJia
 * Date:    2020/7/9 2:27 PM
 * Email:   livsyitian@163.com
 * QQ:      995265288
 * IDE:     PhpStorm
 * User:     
 ****************************************************************/


namespace app\common\observers\point;


use app\common\models\MemberShopInfo;
use app\common\observers\BaseObserver;
use app\common\services\finance\PointService;

class BindMobileAwardObserver extends BaseObserver
{
    public function created($model)
    {
        $this->award($model);
    }

    private function award($model)
    {
        (new PointService([
            'point_mode'        => PointService::POINT_MODE_BIND_MOBILE,
            'member_id'         => $model->member_id,
            'point'             => $model->point,
            'remark'            => '绑定手机奖励积分',
            'point_income_type' => PointService::POINT_INCOME_GET
        ]))->changePoint();
        if ($model->up_point) {
            $tz_member = MemberShopInfo::uniacid()->where('member_id', $model->member_id)->first();
            if ($tz_member->parent_id) {
                (new PointService([
                    'point_mode'        => 192,
                    'member_id'         => $tz_member->parent_id,
                    'point'             => $model->up_point,
                    'remark'            => '绑定手机奖励上级积分',
                    'point_income_type' => PointService::POINT_INCOME_GET
                ]))->changePoint();
            }
        }
    }
}
