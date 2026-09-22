<?php

namespace app\common\models;

class HistoryCityModel extends \app\common\models\BaseModel
{
    protected $table = 'yz_member_history_city';

    protected $guarded = [];

    /**
     * 保存历史记录
     * @param $cityId
     * @param string $cityName
     * @param $memberId
     * @return mixed
     */
    public static function saveData($cityId, string $cityName, $memberId = 0)
    {
        if (empty($cityId)) {
            return null;
        }
        return self::updateOrCreate([
            'uniacid' => \YunShop::app()->uniacid,
            'city_id' => $cityId,
            'member_id' => $memberId ?: \YunShop::app()->getMemberId(),
        ], ['city_name' => $cityName]);
    }
}
