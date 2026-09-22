<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/4/21
 * Time: 下午1:58
 */

namespace app\frontend\models;

use app\common\models\comment\CommentConfig;
use app\frontend\models\goods\Sale;
use app\frontend\modules\goods\models\Comment;

/**
 * Class OrderGoods
 * @package app\frontend\models
 * @property GoodsOption goodsOption
 * @property Goods belongsToGood
 */
class OrderGoods extends \app\common\models\OrderGoods
{

    public function scopeDetail($query)
    {
        return $query->select(['id', 'order_id', 'refund_id','is_refund', 'goods_option_title', 'goods_id', 'goods_price', 'total', 'price', 'title', 'thumb', 'comment_status']);
    }

    public function sale()
    {
        return $this->hasOne(Sale::class, 'goods_id', 'goods_id');
    }

    public static function getMyCommentList($status)
    {
        $list = self::select()->Where('comment_status', $status)->orderBy('id', 'desc')->get();
        return $list;
    }

    public static function getListByMember($status, $member_id, $page, $pageSize)
    {
        $operator = [];
        if ($status == 0) {
            $operator['operator'] = '=';
            $operator['status'] = 0;
        } else {
            $operator['operator'] = '>';
            $operator['status'] = 0;
        }
        return self::select('yz_order_goods.id', 'yz_order_goods.goods_id', 'yz_order_goods.order_id',
            'yz_order_goods.title', 'yz_order_goods.total', 'yz_order_goods.price',
            'yz_order_goods.goods_option_title', 'yz_order_goods.thumb', 'yz_order_goods.comment_id', 'yz_order_goods.comment_status')
            ->where('comment_status', $operator['operator'], $operator['status'])
            ->with([
                'order' => function ($q) {
                    $q->select('id', 'order_sn', 'uid', 'status');
                },
                'hasOneComment' => function ($q) {
                    $q->select('id', 'content', 'type', 'level', 'is_shop', 'additional_comment_id', 'created_at');
                }
            ])
            ->whereHas('order', function ($q) use ($member_id) {
                $q->where('uid', $member_id)->where('status', 3);
            })
            ->leftJoin('yz_comment', 'yz_comment.id', '=', 'yz_order_goods.comment_id')
            ->orderBy('yz_comment.created_at', 'desc')
            ->orderBy('yz_order_goods.order_id', 'desc')
            ->paginate($pageSize,['*'],'page',$page);
    }


}