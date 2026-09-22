<?php

namespace app\common\models\point;


use app\common\models\BaseModel;
use app\common\observers\point\BindMailAwardObserver;

class BindMailAward extends BaseModel
{
    protected $table = 'yz_bind_mail_award_point';

    protected $guarded = [];

    public static function boot()
    {
        parent::boot();
        self::observe(new BindMailAwardObserver());
    }

    /**
     * @param int $memberId
     *
     * @return bool
     */
    public static function isAwarded($memberId)
    {
        return !!static::where('member_id', $memberId)->first();
    }

    /**
     * @param int $memberId
     * @param float $point
     * @param float $upPoint
     *
     * @return bool
     */
    public static function awardMember($memberId, $point, $upPoint)
    {
        return (new static())->fill([
            'uniacid'   => \YunShop::app()->uniacid,
            'point'     => $point,
            'up_point'     => $upPoint,
            'member_id' => $memberId
        ])->save();
    }
}
