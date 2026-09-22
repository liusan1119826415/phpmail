<?php

namespace app\common\services\finance;

use app\common\facades\Setting;

abstract class DeductPointService
{
    protected $deductBalance;
    protected $deductPoint;

    /**
     * @param $withdrawMoney
     * @return float|int
     */
    public function getNeedDeductPoint($money = 0)
    {
        // (提现余额 / 比例余额) * 比例积分 = 需要扣除的积分
        $need_deduct_point = bcmul(bcdiv($money, $this->deductBalance, 2), $this->deductPoint, 2);
        return floatval($need_deduct_point) ?: 0;
    }

    /**
     * 积分记录
     * @param float $point 积分
     * @param string $pointMode 类型
     * @param string $remark 备注
     * @param int $pointIncomeType 减少或者删除
     * @param int $memberId
     * @param int $relationId 关联表id
     * @return \app\common\models\finance\PointLog|bool
     * @throws \app\common\exceptions\ShopException
     */
    protected function changePoint($point, string $pointMode, string $remark, int $pointIncomeType, $memberId = 0, $relationId = 0)
    {
        $point = $pointIncomeType == 1 ? $point : -$point;
        $change_data = [
            'point_mode' => $pointMode,
            'member_id' => $memberId,
            'point' => $point,
            'remark' => $remark,
            'point_income_type' => $pointIncomeType,
            'relation_id' => $relationId
        ];
        return (new PointService($change_data))->changePoint();
    }

    /**
     * 获取积分名称
     * @return string
     */
    public static function getPointName(): string
    {
        return Setting::get('shop.lang.zh_cn.member_center.credit1') ?: "积分";
    }

    /**
     * 余额名称
     * @return string
     */
    public static function getBalanceName(): string
    {
        return Setting::get('shop.lang.zh_cn.member_center.credit') ?: '余额';
    }
}
