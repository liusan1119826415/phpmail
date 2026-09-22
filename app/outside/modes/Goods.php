<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/4/12
 * Time: 9:40
 */

namespace app\outside\modes;

use app\common\services\wordanalysis\Analysis;

class Goods extends \app\common\models\Goods
{

    public static function getList($search = [])
    {
        $model = self::uniacid()
            ->select(['id', 'plugin_id','title', 'thumb', 'sku', 'goods_sn', 'price', 'stock','status',
                'market_price','cost_price','has_option','created_at','weight','volume', 'real_sales',

            ]);


        //上架有货商品：1有库存 2没库存
        if ($search['in_stock']) {
            if ($search['in_stock'] == 1) {
                $model->where('yz_goods.status', 1)->where('yz_goods.stock', '>', 0);
            } else {
                $model->where('yz_goods.status', 1)->where('yz_goods.stock', '=', 0);
            }
        }

        if (isset($search['status']) && is_numeric($search['status'])) {
            $model->where('status', $search['status']);
        }

        if ($search['goods_title']) {
            $model->where('yz_goods.title','like','%'.$search['goods_title'].'%');
        }


        if ($search['min_price']) {
            $model->where('price', '>', $search['min_price']);
        }

        if ($search['max_price']) {
            $model->where('price', '<', $search['max_price']);
        }

        if ($search['plugin']) {
            $pluginIds = explode(',', $search['plugin']);
            if ($pluginIds) {
                $model->whereIn('plugin_id', $pluginIds);
            } else {
                $model->where('plugin_id', $search['plugin']);
            }
        } else {
            $model->whereInPluginIds();
        }


        $model->with(['hasManyOptions' => function ($q) {
            return $q->select(['id','goods_id','title','thumb','product_price','market_price','cost_price',
            'stock','weight','volume']);
        }]);


        return $model;
    }
}