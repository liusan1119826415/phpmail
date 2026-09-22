<?php

namespace app\frontend\modules\goods\controllers;
use app\common\models\Goods;
class SearchController extends ApiController
{
    protected $publicAction = [];
    protected $ignoreAction = [];




    public function index()
    {

        app('db')->cacheSelect = false;
        $goods_model = new Goods;
        $requestSearch = \YunShop::request()->search;
        $order_field = \YunShop::request()->order_field;

        if (!in_array($order_field, ['price', 'show_sales', 'comment_num', 'min_price', 'max_price'])) {
            $order_field = 'display_order';
        } else {
            if ($order_field == 'show_sales') {
                //排序改为虚拟销量
                $order_field = 'total_sales';
            }
        }


        $order_by = (\YunShop::request()->order_by == 'asc') ? 'asc' : 'desc';
        if ($requestSearch) {
            $requestSearch = array_filter($requestSearch, function ($item) {
                return !empty($item) && $item !== 0 && $item !== "undefined";
            });
            $categorySearch = array_filter(\YunShop::request()->category, function ($item) {
                return !empty($item);
            });
            if ($categorySearch) {
                $requestSearch['category'] = $categorySearch;
            }
        }
        //增加默认搜索不隐藏的商品
        $requestSearch['is_hide'] = 1;
        $ims = DB::getTablePrefix();

        //不要添加修改字段，乱添加字段会导致执行某些搜索条件时报错
        $goods_select = "{$ims}yz_goods.has_option,{$ims}yz_goods.stock,{$ims}yz_goods.id,{$ims}yz_goods.thumb,{$ims}yz_goods.id as goods_id,";
        $goods_select .= "plugin_id,real_sales+virtual_sales total_sales,market_price,price,min_price,max_price,cost_price,title";

        $option_select = 'goods_id,product_price,market_price,stock,cost_price';
        $build = $goods_model->SearchList($requestSearch)
            ->selectRaw($goods_select)
            ->with(['hasManyOptions' => function ($query) use ($option_select) {
                $query->selectRaw($option_select);
            }])
            ->where("yz_goods.status", 1);
        if (app('plugins')->isEnabled('good-style')) {
            $build = $build->with(['goodStyle' => function ($query) {
                $query->selectRaw('goods_id,current_logo,current_name');
            }]);
        }




        $list = $build->orderBy($order_field, $order_by)
            ->orderBy('yz_goods.id', 'desc')
            ->paginate(20);
        if ($list->isEmpty()) {
            return $this->errorJson('没有找到商品.');
        }
        //TODO 租赁插件是否开启 $lease_switch
        $lease_switch = LeaseToyGoods::whetherEnabled();
        //由于之前 foreach 太多了 合在一起
        $list->map(function ($item) use ($lease_switch) {
            $item->thumb = yz_tomedia($item->thumb);
            //租赁商品
            $this->goods_lease_set($item, $lease_switch);
            // 商品标签
            $this->setGoodsLabel($item);
            $item->id = $item->goods_id;
            if (Setting::get('goods.profit_show_status')) {
                if ($item->has_option) {
                    if ($item->hasManyOptions && $item->hasManyOptions->isNotEmpty()) {
                        $item->hasManyOptions = $item->hasManyOptions->each(function ($option) {
                            $option->goods_profit = bcsub($option->product_price, $option->cost_price, 2);
                            if (bccomp($option->goods_profit, 0, 2) == -1) {
                                $option->goods_profit = 0;
                            }
                        });
                        $item->min_goods_profit = $item->hasManyOptions->min('goods_profit') ?: 0;
                    } else {
                        $item->min_goods_profit = 0;
                    }
                } else {
                    $item->goods_profit = bcsub($item->price, $item->cost_price, 2);
                    if (bccomp($item->goods_profit, 0, 2) == -1) {
                        $item->goods_profit = 0;
                    }
                    $item->min_goods_profit = $item->goods_profit;
                }
            }
            //会员vip价格
            $item->vip_price = $item->vip_price;
            $item->vip_next_price = $item->vip_next_price;
            $item->vip_level_status = $item->vip_level_status;
            $item->price_level = $item->price_level;
            $item->is_open_micro = $item->is_open_micro;
            $item->goods_points = $this->setGoodPoints($item);

            if (app('plugins')->isEnabled('points-price-display') && \Setting::get('points-price-display.set.list_show')) {
                $deduct = PointsPrice::maximumDiscount($item);
                if ($deduct['is_has']) {
                    $item->points_price_display = [
                        'deduction' => $deduct['value'],
                        'goods_price' => $deduct['price'],
                    ];
                }
            }

            if (\Setting::get('shop.category.category_show_in_list')) {
                $item->first_cat_name = GoodsCategory::getGoodsFirstCatName($item->id);
            }
            return $item;
        });
        $list = $list->toArray();
        foreach ($list['data'] as &$v) {
            if ($v['has_option'] && $v['has_many_options']) {
                $v['stock'] = array_sum(array_column($v['has_many_options'], 'stock'));
            }
        }

        $category_data = [
            'names' => '01',
            'type' => 'goodsList',
        ];

        if (app('plugins')->isEnabled('decorate') && \Setting::get('plugin.decorate.is_open') == 1) {
            $view_set = \Yunshop\Decorate\models\DecorateTempletModel::uniacid()
                ->whereIn('code', ['goodsList01', 'goodsList02', 'goodsList03'])
                ->where('type', 5)
                ->where('is_default', 1)
                ->first();
            $tmp = [
                'goodsList01' => '01',
                'goodsList02' => '02',
                'goodsList03' => '03',
            ];
            $category_data = $view_set ? ['names' => $tmp[$view_set->code], 'type' => 'goodsList'] : $category_data;
            if ($view_set) {
                $list['data'] = $this->getGoodsCouponSale($list['data']);
                //团队销售佣金一级分销
                if (app('plugins')->isEnabled('team-sales')) {
                    $list['data'] = GoodsListService::getFirstDividend($list['data']);
                }
            }
            if ($category_data['names'] == '03') {
                if (app('plugins')->isEnabled('love')) {//爱心值天天兑价
                    $list['data'] = \Yunshop\Love\Frontend\Models\GoodsLove::setGoodsLove($list['data']);
                }
            }
        } elseif (app('plugins')->isEnabled('designer')) {
            //商品分类模板
            $view_set = ViewSet::uniacid()->where('type', 'goodsList')->select('names', 'type')->first();
            $category_data = $view_set ?: $category_data;
            if (!empty($view_set) && $view_set->names == '02') {
                $list['data'] = $this->getGoodsCouponSale($list['data']);
                //团队销售佣金一级分销
                if (app('plugins')->isEnabled('team-sales')) {
                    $list['data'] = GoodsListService::getFirstDividend($list['data']);
                }
            }
        }
        //增加商品链接
        if (app('plugins')->isEnabled('goods-link')) {
            $list['data'] = GetGoodsDocService::getDoc($list['data']);
        }
        //通证价计算
        if (app('plugins')->isEnabled('pass-price') && \Setting::get('pass-price.set.plugin_enable')) {
            $list['data'] = \Yunshop\PassPrice\services\PassPriceService::getPass($list['data']);
        }
        //积分商城
        if (app('plugins')->isEnabled('point-mall')) {
            $list['data'] = \Yunshop\PointMall\api\models\PointMallGoodsModel::setPointGoods($list['data']);
        }

        // 爱心值价格显示
        if (app('plugins')->isEnabled('love-price-display')) {
            $list['data'] = \Yunshop\LovePriceDisplay\services\LovePrice::searchGoodsData($list['data']);
        }
        //让利比例
        if (app('plugins')->isEnabled('concession-ratio') && in_array(1, \Yunshop\ConcessionRatio\common\services\SettingService::contributionShowPage())) {
            $list['data'] = \Yunshop\ConcessionRatio\common\services\CommonService::goodsListContributionCalculate($list['data']);
        }

        $list['goods_template'] = $category_data;
        $goods_style_set = Setting::get('plugin.good_style');
        if ($goods_style_set) {
            $goods_style_set['current_thumb'] = yz_tomedia($goods_style_set['current_thumb']);
            $goods_style_set['member_thumb'] = yz_tomedia($goods_style_set['member_thumb']);
        }
        $list['goods_style_set'] = $goods_style_set;
        return $this->successJson('成功', $list);
    }


}
