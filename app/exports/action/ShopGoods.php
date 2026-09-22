<?php

namespace app\exports\action;

use app\backend\modules\goods\models\Goods;
use app\exports\generator\ShopGoodsFromGenerator;

class ShopGoods extends BaseAction
{
    public $type = 'shopGoods';

    public $name = '自营商品';

    public $ext = 'xlsx';

    public function preResult($header,$columns): array
    {
        if ($header['category']) {
            $header_temp = [];
            $columns_temp = [];
            foreach ($header as $key => $value) {
                if ($key == 'category') {
                    $header_temp = array_merge($header_temp, ['first_category' => '一级分类', 'second_category' => '二级分类', 'third_category' => '三级分类']);
                    $columns_temp = array_merge($columns_temp, ['first_category', 'second_category', 'third_category']);
                } else {
                    $header_temp[$key] = $value;
                    $columns_temp[] = $key;
                }
            }
            $header = $header_temp;
            $columns = $columns_temp;
        }
        return [$header,$columns];
    }


    public function getData($request): \Generator
    {
        $requestSearch = $request->search;
        if ($requestSearch) {
            $requestSearch = array_filter($requestSearch, function ($item) {
                return $item !== '';// && $item !== 0;
            });
            $categorySearch = array_filter($request->category, function ($item) {
                if (is_array($item)) {
                    return !empty($item[0]);
                }
                return !empty($item);
            });
            if ($categorySearch) {
                $requestSearch['category'] = $categorySearch;
            }
        }
        $tab_state = $request->tab_state;
        $query = Goods::Search($requestSearch)->with(['hasOneSmallCodeUrl'])->pluginIdShow()->state($tab_state);

        $query->with(['hasManyOptions' => function ($query) {
            return $query->select('goods_id', 'title', 'product_price', 'market_price', 'cost_price', 'goods_sn', 'product_sn','stock');
        }])->with(['hasManyGoodsFilter' => function ($query) {
            return $query->with([
                'hasOneSearchFilter' => function ($query1) {
                    $query1->select('id', 'name');
                }
            ]);
        }])->with(['hasOneSale' => function ($query) {
            return $query->select('goods_id', 'max_point_deduct', 'min_point_deduct');
        }])->with(['hasOneGoodsDispatch' => function ($query) {
            return $query->select('goods_id', 'dispatch_type', 'dispatch_price');
        }])->with('belongsToCategorys');
        //排序
        $order_by = $request->order_by;
        if ($order_by) {
            foreach ($order_by as $by_key => $by_value) {
                if ($by_value) {
                    $query->orderBy('yz_goods.' . $by_key, $by_value);
                }
            }
        } else {
            $query->orderBy('yz_goods.display_order', 'desc');
        }

        if ($requestSearch['min_ratio']) {
            $query->whereRaw('(price-cost_price)/price*100 > ' . (float)$requestSearch['min_ratio']);
        }
        if ($requestSearch['max_ratio']) {
            $query->whereRaw('(price-cost_price)/price*100 < ' . (float)$requestSearch['max_ratio']);
        }

        if (!empty($requestSearch['source_id'])) {
            $query = $query->whereHas('sourceGoods', function ($query) use ($requestSearch) {
                $query->where('source_id', $requestSearch['source_id']);
            });
        }
        $list = $query->orderBy('display_order', 'desc')->orderBy('yz_goods.id', 'desc');


        $categoryService = new \app\backend\modules\goods\models\Category();
        foreach ($list->get() as $key => $value) {
            $cate = [];
            foreach ($value->belongsToCategorys as $v) {
                $cate[] = $categoryService->getCateOrderByLevel($v['category_ids']);
            }
            $label = [];
            foreach ($value->hasManyGoodsFilter as $goodsFilter) {
                if ($goodsFilter->hasOneSearchFilter) {
                    $label[] = $goodsFilter->hasOneSearchFilter->name;
                }
            }
            $label = implode(',', $label);
            $options = [];
            if (count($value->hasManyOptions)) {
                $options = $value->hasManyOptions->toArray();
            }
            yield $key => [
                'id' => $value->id,
                'display_order' => $value->display_order,
                'title' => $value->title,
                'label' => $label,
                'free_shipping' => $value->hasOneGoodsDispatch->dispatch_type == 1 && $value->hasOneGoodsDispatch->dispatch_price <= 0 ? '是' : '否',
                'point_max_deduction' => $value->hasOneSale ? $value->hasOneSale->max_point_deduct : '',
                'point_min_deduction' => $value->hasOneSale ? $value->hasOneSale->min_point_deduct : '',
                'brand_id' => $value->hasOneBrand->name,
                'alias' => $value->alias,
                'first_category' => implode('|', array_column($cate, 0)) ,
                'second_category' => implode('|', array_column($cate, 1)),
                'third_category' => implode('|', array_column($cate, 2)),
                'status' => $value->status_name,
                'sku' => $value->sku,
                'real_sales' => $value->real_sales,
                'option' => count($options) ? array_column($options, 'title') : ['单规格'],
                'stock' => count($value->hasManyOptions) ? array_column($value->hasManyOptions->toArray(), 'stock') : [$value->stock],
                'price' => count($options) ? array_column($options, 'product_price') : [$value->price],
                'market_price' => count($options) ? array_column($options, 'market_price') : [$value->market_price],
                'cost_price' => count($options) ? array_column($value->hasManyOptions->toArray(), 'cost_price') : [$value->cost_price],
                'goods_sn' => count($options) ? array_column($value->hasManyOptions->toArray(), 'goods_sn') : [$value->goods_sn],
                'product_sn' => count($options) ? array_column($value->hasManyOptions->toArray(), 'product_sn') : [$value->product_sn],

            ];
        }
    }

    public function getFromGenerator($data)
    {
        return new ShopGoodsFromGenerator($data);
    }


}
