<?php


namespace app\frontend\modules\coupon\models;


class OrderCoupon extends \app\common\models\Coupon
{

    /**
     * 临时解决办法，后期有空修改
     * @var array
     */
    protected $hidden = [
        'storeids',
        'uniacid',
        'cat_id',
        'get_type',
        'level_limit',
        'get_max',
        'get_limit_max',
        'get_limit_type',
        'use_type',
        'return_type',
        'coupon_type',
        'time_limit',
        'time_days',
        'time_start',
        'time_end',
        'back_type',
        'back_money',
        'back_credit',
        'back_redpack',
        'back_when',
        'thumb',
        'desc',
        'resp_desc',
        'resp_thumb',
        'resp_title',
        'resp_url',
        'credit',
        'usecredit2',
        'remark',
        'descnoset',
        'display_order',
        'supplier_uid',
        'getcashier',
        'cashiersids',
        'cashiersnames',
        'category_ids',
        'categorynames',
        'goods_names',
        'storenames',
        'member_tags_ids',
        'member_tags_names',
        'getstore',
        'getsupplier',
        'supplierids',
        'suppliernames',
        'createtime',
        'created_at',
        'updated_at',
        'deleted_at',
        'is_complex',
        'plugin_id',
        'use_conditions',
        'is_integral_exchange_coupon',
        'exchange_coupon_integral',
        'content',
    ];

}