<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/2/23
 * Time: 上午10:43
 */

namespace app\frontend\modules\member\models;

use app\common\models\GoodsOption;

class MemberHistory extends \app\common\models\MemberHistory
{

    /**
     *  不可填充字段.
     *
     * @var array
     */
    protected $guarded = [''];

    public function goods()
    {
        return $this->hasOne(\app\common\modules\shop\ShopConfig::current()->get('goods.models.commodity_classification'), 'id', 'goods_id');
    }

    public function goodsOneOption()
    {
        return $this->hasOne(GoodsOption::class, 'goods_id', 'goods_id');
    }

    /*
     *
     * @param int memberId
     * @param int goodsId
     *
     * @return object */
    public static function getHistoryByGoodsId($memberId, $goodsId)
    {
        return static::uniacid()
            ->whereBetween('created_at', [strtotime('today'), strtotime('today') + 86399])
            ->where('member_id', $memberId)
            ->where('goods_id', $goodsId)
            ->first();
    }

    /*
     *
     * @param int memberId
     * @param int goodsId
     *
     * @return object */
    public static function getHistoryById($historyId)
    {
        return static::uniacid()->where('id', $historyId)->first();
    }

    /**
     * Get member browsing records
     *
     * @param int $memberId 会员ID
     *
     * @return object $list
     */
    public static function getMemberHistoryList($memberId)
    {
        $data = MemberHistory::uniacid()
            ->select()
            ->selectRaw("FROM_UNIXTIME(created_at,'%Y-%m-%d') as date_group")
            ->where('member_id', $memberId)
            ->has('goods')
            ->with([
                'goods' => function ($query) {
                    return $query->select('id', 'thumb', 'price', 'market_price', 'title', 'has_option');
                },
            ])
            ->addSelect([
                'option_price' => GoodsOption::whereColumn('yz_member_history.goods_id', 'yz_goods_option.goods_id')
                    ->select('product_price')
                    ->orderBy('product_price', 'asc')
                    ->limit(1)
            ])
            ->orderBy('updated_at', 'desc')
            ->paginate();
        foreach ($data as &$itme) {
            $itme['vip_level_status'] = $itme->goods->vip_level_status;
        }

        $data = $data->toArray();
        if (app('plugins')->isEnabled('point-mall')) {
            $data['data'] = \Yunshop\PointMall\api\models\PointMallGoodsModel::setHistoryPointGoods($data['data']);
        }
        $data['member_histories'] = $data['data'];
        unset($data['data']);

        if (!empty($data['member_histories'])) {
            foreach ($data['member_histories'] as &$value) {

                //过滤空数组
                if (!empty($value['goods']['thumb'])) {
                    $value['goods']['thumb'] = yz_tomedia($value['goods']['thumb']);
                }
            }
        }

        return $data;
    }

    /**
     * Get member browsing records
     *
     * @param int $memberId 会员ID
     *
     * @return object $list
     */
    public static function getoldMemberHistoryList($memberId)
    {
        $data = MemberHistory::uniacid()
            ->select()
            ->selectRaw("FROM_UNIXTIME(created_at,'%m月%d日') as date_group")
            ->where('member_id', $memberId)
            ->whereBetween('created_at', [strtotime('- 1year'), time()])
            ->has('goods')
            ->with(['goods' => function ($query) {
                return $query->select('id', 'thumb', 'price', 'market_price', 'title');
            }])
            ->orderBy('updated_at', 'desc')
            ->get();
        if (app('plugins')->isEnabled('point-mall')) {
            $data = \Yunshop\PointMall\api\models\PointMallGoodsModel::setHistoryPointGoods($data);
        }
        foreach ($data as &$itme) {
            $itme->vip_level_status = $itme->goods->vip_level_status;
            if (!empty($itme->goods->thumb)) {
                $itme->goods->thumb = yz_tomedia($itme->goods->thumb);
            }
        }
        //按照日期分组并且排序（倒序）
        return $data->groupBy('date_group')->toArray();
    }

    public static function getMemberHistoryCount($memberId)
    {
        $data = MemberHistory::uniacid()
            ->where('member_id', $memberId)
            ->has('goods')
            ->count();
        return $data;
    }
}
