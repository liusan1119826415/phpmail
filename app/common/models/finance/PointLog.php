<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/4/10
 * Time: 下午5:47
 */

namespace app\common\models\finance;


use app\common\models\BaseModel;
use app\common\models\Member;
use app\common\models\Order;
use app\common\observers\point\PointChangeObserver;
use app\common\services\finance\PointService;

/**
 * @method static self searchMember($search = [])
 * Class PointLog
 * @package app\common\models\finance
 */
class PointLog extends BaseModel
{
    protected $table = 'yz_point_log';

    protected $guarded = [''];

    protected $search_fields = ['id'];

    protected $appends = ['source_name'];

    public static function boot()
    {
        parent::boot();
        self::observe(PointChangeObserver::class);
    }

    public function hasOneOrder()
    {
        return $this->hasOne(Order::class, 'id', 'order_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id', 'uid');
    }

    public function getSourceNameAttribute()
    {
        return $this->getSourceNameComment($this->attributes['point_mode']);
    }

    public function getSourceNameComment($sourceAttribute)
    {
        return isset($this->sourceComment()[$sourceAttribute]) ? $this->sourceComment()[$sourceAttribute] : "未知变动";
    }

    /**
     * @param static $query
     * @param array $search
     */
    public function scopeSearch($query, $search = [])
    {
        if ($search['source']) {
            $query->where("point_mode", $search['source']);
        }
        if ($search['income_type']) {
            $query->where("point_income_type", $search['income_type']);
        }
        if ($search['search_time']) {
            $query->whereBetween('created_at', [strtotime($search['time']['start']), strtotime($search['time']['end'])]
            );
        }
        $query->searchMember($search);
    }

    /**
     * @param static $query
     * @param array $search
     */
    public function scopeSearchMember($query, $search = [])
    {
        if ($search['member'] || ($search['level_id'] !== '' && $search['level_id'] !== null) || $search['group_id'] || $search['member_id']) {
            $query->whereHas('member', function ($query) use ($search) {
                /**
                 * @var Member $query
                 */
                $query->search($search);
            });
        }
    }

    /**
     * todo 原有机制优化，临时使用，可以优化为 Key => value，自动加载模式
     *
     * @return array
     */
    public function sourceComment()
    {
        return PointService::getAllSourceName();
    }

    protected static function getBalanceName()
    {
        $lang = \Setting::get('shop.lang', ['lang' => 'zh_cn']);
        return $lang['member_center']['credit'] ?: '余额';
    }

    private function transferLoveName()
    {
        return app('plugins')->isEnabled('love') ? '转入' . LOVE_NAME : '转入爱心值';
    }

    private function loveDeductionName()
    {
        return app('plugins')->isEnabled('love') ? LOVE_NAME . '提现扣除' : '爱心值提现扣除';
    }

    private function LoveBuyDeductName()
    {
        return app('plugins')->isEnabled('love') ? LOVE_NAME . '购物抵扣扣除' : '爱心值购物抵扣扣除';
    }

    private function loveTransferName()
    {
        return app('plugins')->isEnabled('love') ? LOVE_NAME . '转赠-转入' : '爱心值转赠-转入';
    }

    private function loveActualReceipt()
    {
        return app('plugins')->isEnabled('love') ? LOVE_NAME . '提现扣除(实际到账)' : '爱心值提现扣除(实际到账)';
    }

    private function signAwardName()
    {
        return app('plugins')->isEnabled('sign') ? trans('Yunshop\Sign::sign.plugin_name') . '奖励' : '签到奖励';
    }

    private function frozeAwardName()
    {
        return app('plugins')->isEnabled('sign') ? trans('Yunshop\Froze::froze.name') . '奖励' : '冻结币奖励';
    }

    private function integralTransferName()
    {
        $point_name = \Setting::get('shop.shop')['credit1'] ?: '积分';
        return app('plugins')->isEnabled('integral') ? INTEGRAL_NAME . "转化{$point_name}" : '消费积分转化积分';
    }

    private function pointTransferIntegralName()
    {
        $point_name = \Setting::get('shop.shop')['credit1'] ?: '积分';
        return app('plugins')->isEnabled('integral') ? $point_name . '转化' . INTEGRAL_NAME : "{$point_name}转化消费积分";
    }

    private function LoveFrozeActiveName()
    {
        return app('plugins')->isEnabled('love') ? '冻结'.LOVE_NAME.'激活' : '冻结爱心值激活';
    }

    private function poolResetName()
    {
        $point_name = \Setting::get('shop.shop')['credit1'] ?: '积分';
        return '清零设置-' . $point_name . '清零';
    }

    private function poolClearName()
    {
        $point_name = \Setting::get('shop.shop')['credit1'] ?: '积分';
        return "加速池扣除({$point_name}消耗)";
    }

    private function loveTransformationPointName()
    {
        $love_name = app('plugins')->isEnabled('love') ? LOVE_NAME : '爱心值';
        $point_name = \Setting::get('shop.shop')['credit1'] ?: '积分';
        return $love_name . '转换' . $point_name;
    }

    private function frozeCouponGiveName()
    {
        $point_name = \Setting::get('shop.shop')['credit1'] ?: '积分';
        return '冻结券转' . $point_name;
    }

    //todo----------------------------- 以下代码可以优化模型使用 --------------------------------

    public function hasOneMember()
    {
        return $this->hasOne(Member::class, 'uid', 'member_id');
    }

    public static function getPointLogList($search)
    {
        return PointLog::lists($search);
    }

    public function scopeLists($query, $search)
    {
        $query->search($search);
        $builder = $query->with([
            'hasOneMember',
        ]);
        return $builder;
    }
}
