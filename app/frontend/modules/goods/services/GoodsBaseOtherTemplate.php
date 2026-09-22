<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/10/30
 * Time: 15:00
 */

namespace app\frontend\modules\goods\services;

use app\common\models\Goods;
use app\common\models\SearchFiltering;

class GoodsBaseOtherTemplate
{
    /**
     * @var Goods
     */
    public $goods;


    public $externalParam;

    public function __construct(Goods $goods)
    {
        $this->goods = $goods;
    }

    final public function externalParam($data)
    {
        $this->externalParam = $data;
    }

    final public function setAttribute($key, $value)
    {
        $this->setAttribute($key, $value);
    }

    public function mergeExtraData()
    {
        $this->goods->vip_price = $this->goods->vip_price;
        $this->goods->vip_next_price = $this->goods->vip_next_price;
        $this->goods->price_level = $this->goods->price_level;
        $this->goods->is_open_micro = $this->goods->is_open_micro;

        $filter_ids = $this->goods->hasManyGoodsFilter->pluck('filtering_id')->all();
        if (!empty($filter_ids)) {
            $label_list = SearchFiltering::getAllEnableFiltering()->whereIn('id', $filter_ids)
                ->where('is_front_show', 1)->values()->toArray();

            $this->setAttribute('label_list',$label_list);

        }

    }
}