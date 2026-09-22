<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/4/17
 * Time: 15:46
 */

namespace app\outside\modules\coupon\controllers;

use app\common\exceptions\ApiException;
use app\common\helpers\Url;
use app\common\models\Coupon;
use app\common\models\MemberCoupon;
use app\common\models\Store;
use app\outside\controllers\OutsideController;
use app\outside\modes\Goods;

class CouponController extends OutsideController
{

    /**
     * @return Coupon
     */
    public function couponModel($search)
    {
        $model = Coupon::uniacid()->pluginId()
            ->select(['id','display_order','name', 'enough', 'coupon_method',
                'deduct', 'discount', 'get_type','get_max', 'created_at',
                'total','is_complex','use_type', 'coupon_img','status',
                'time_limit', 'time_days', 'time_start','time_end', 'wait_days',
                'category_ids', 'categorynames', 'goods_ids', 'goods_names',
                'use_conditions'
            ]);

        if (isset($search['status']) && is_numeric($search['status'])) {
            $model->where('status', $search['status']);
        }

        if ($search['keyword']) {
            $model->where('name', 'like', '%'.$search['keyword'].'%');
        }

        if(!empty($search['start_time']) && !empty($search['end_time'])){
            $model->whereBetween('created_at', [$search['start_time'], $search['end_time']]);
        }

        $model->whereNotIn('use_type', [
            Coupon::COUPON_SUPPLIER_USE,Coupon::COUPON_STORE_USE,Coupon::COUPON_SINGLE_STORE_USE,
            Coupon::COUPON_ONE_HOTEL_USE,Coupon::COUPON_MORE_HOTEL_USE,Coupon::COUPON_GOODS_AND_STORE_USE
            ]);

        return $model;
    }

    public function list()
    {


        $pageSize = 15;

        $search = [
            'keyword' => request()->input('keyword'),
            'status' => request()->input('status'),
            'start_time' => request()->input('start_time'),
            'end_time' => request()->input('end_time'),
        ];

        $page = $this->couponModel($search)
            ->orderBy('display_order', 'desc')
            ->orderBy('updated_at', 'desc')
            ->paginate($pageSize);


        $page->makeHidden([ 'category_ids', 'categorynames', 'goods_ids', 'goods_names', 'use_conditions']);

        $page->map(function (Coupon $coupon) {
            $coupon->get_total = MemberCoupon::uniacid()->where("coupon_id", $coupon->id)->count();
            $coupon->use_total = MemberCoupon::uniacid()->where("coupon_id", $coupon->id)->where("used", 1)->count();

            //获取优惠券的第一个商品地址
            $coupon->goods_url = empty($coupon->goods_ids) ? '' : Url::absoluteApp('goods/'. $coupon->goods_ids[0]);

            $this->getCouponData($coupon);
        });



        $array = array_only($page->toArray(),['total','current_page','data','per_page','last_page']);

        return $this->successJson('list', $array);
    }

    /**
     * 过滤不满足条件的优惠卷 &
     * 添加"是否可领取" & "是否已抢光" & "是否已领取"的标识
     * @param $coupons
     * @param $search
     * @return mixed
     */
    public function getCouponData(Coupon $coupon)
    {
        //添加优惠券使用范围描述
        switch ($coupon->use_type) {
            case Coupon::COUPON_SHOP_USE:
                $coupon->api_limit = '商城通用';
                break;
            case Coupon::COUPON_CATEGORY_USE:
                $coupon->api_limit = '适用于下列分类: '.implode(',', $coupon['categorynames']);
                break;
            case Coupon::COUPON_GOODS_USE:
                $coupon->api_limit = '适用于下列商品: '.implode(',', $coupon['goods_names']);
                break;
            case Coupon::COUPON_EXCHANGE_USE:
                $coupon->api_limit = '适用于下列商品: '.implode(',', $coupon['goods_names']);
                break;
            case Coupon::COUPON_GOODS_AND_STORE_USE:
                $coupon->api_limit = '适用范围: ';
                $use_condition = unserialize($coupon['use_conditions']);
                if (empty($use_condition)) {
                    $coupon->api_limit .= '无适用范围';
                }
                if (app('plugins')->isEnabled('store-cashier')) {
                    if ($use_condition['is_all_store'] == 1) {
                        $coupon->api_limit .= "全部门店";
                    } else {
                        $coupon->api_limit .= '门店:'.implode(',', Store::uniacid()->whereIn('id', $use_condition['store_ids'])->pluck('store_name')->all());
                    }
                }
                if ($use_condition['is_all_good'] == 1) {
                    $coupon->api_limit .= "平台自营商品";
                } else {
                    $coupon->api_limit .= '商品:'.implode(',', Goods::uniacid()->whereIn('id', $use_condition['good_ids'])->pluck('title')->all());
                }
                break;
        }


        return $coupon;
    }
}