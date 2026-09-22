<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/10/24
 * Time: 15:30
 */

namespace app\frontend\modules\goods\controllers;

use app\common\models\Goods;
use app\frontend\modules\goods\services\ClassifyService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cookie;
use app\common\components\BaseController;
use app\common\components\ApiController;
use app\common\exceptions\AppException;
use app\common\helpers\Url;
use app\common\models\GoodsCategory;
use app\common\helpers\PaginationHelper;
use app\frontend\modules\goods\models\Category;
use app\common\services\goods\LeaseToyGoods;
use app\common\models\Slide;

class ClassifyController extends BaseController
{
    public function category($isAjax = true)
    {
        $search['parent_id'] = intval(request()->input('parent_id',0));

        $search['plugin_id'] =  intval(request()->input('plugin',0));

        $categoryList = Category::selectQuery($search)->paginate(20);

        if ($categoryList->isNotEmpty()) {
            $categoryList->map(function ($category,$key) {
                $category->thumb = yz_tomedia($category->thumb);
                $category->adv_img = yz_tomedia($category->adv_img);
            });
        }

        if ($isAjax) {
            return $this->successJson('categoryList', $categoryList);
        }

        return $categoryList;

    }

    public function recommendList($isAjax = true)
    {
        $plugin_id =  intval(request()->input('plugin',0));


        $recommendCategory = Category::uniacid()->recommendQuery()
            ->where('plugin_id', $plugin_id)->paginate(20);

        if ($recommendCategory->isNotEmpty()) {
            $recommendCategory->map(function ($recommendCategory,$key) {
                $recommendCategory->thumb = yz_tomedia($recommendCategory->thumb);
                $recommendCategory->adv_img = yz_tomedia($recommendCategory->adv_img);
            });
        }

        if ($isAjax) {
            return $this->successJson('recommendList', $recommendCategory);
        }

        return $recommendCategory;

    }

    public function home()
    {
        $data['shop_set'] = $this->shopSet();
        $data['decorate_config'] = (new ClassifyService())->unifiedFormat(request());

        $data['ads'] = $this->getAds();
        $data['recommendList'] = empty($data['shop_set']['cat_class']) ? $this->recommendList(false): [];
        $data['categoryList'] = $this->category(false);

        $data['member_cart'] = [];
        if ($data['decorate_config']['cart_style']) {
            $data['member_cart'] = $this->getMemberCart();
        }

        return $this->successJson('获取分类数据成功!',$data);

    }

    protected function shopSet()
    {
        $set = \Setting::get('shop.category')?:[];

        $categorySet = array_merge(['cat_brand'=>0,'cat_class'=>0,'cat_level'=>2], $set);

        if ($categorySet['cat_adv_img']) {
            $categorySet['cat_adv_img'] = yz_tomedia($categorySet['cat_adv_img']);
        }

        //只有分类中心模板是小程序模板的时候提供给前端进行判断
        $categorySet['web_design_enable'] = app('plugins')->isEnabled('web-design')?'1':'0';

        return $categorySet;
    }

    protected function getMemberCart()
    {
        // 会员未登录，购物车没数据的
        try {
            $uid = \app\frontend\models\Member::current()->uid;
        } catch (\app\common\exceptions\MemberNotLoginException $e) {
            return [];
        }

        $cartList = app('OrderManager')->make('MemberCart')->carts()
            ->where('member_id', $uid)
            ->pluginId()
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
        foreach ($cartList as $key => $cart) {
            $cartList[$key]['option_str'] = '';
            $cartList[$key]['goods']['thumb'] = yz_tomedia($cart['goods']['thumb']);
            if (!empty($cart['goods_option'])) {
                //规格数据替换商品数据
                if ($cart['goods_option']['title']) {
                    $cartList[$key]['option_str'] = $cart['goods_option']['title'];
                }
                if ($cart['goods_option']['thumb']) {
                    $cartList[$key]['goods']['thumb'] = yz_tomedia($cart['goods_option']['thumb']);
                }
                if ($cart['goods_option']['market_price']) {
                    $cartList[$key]['goods']['price'] = $cart['goods_option']['product_price'];
                }
                if ($cart['goods_option']['market_price']) {
                    $cartList[$key]['goods']['market_price'] = $cart['goods_option']['market_price'];
                }
            }
        }
        return $cartList;
    }

     /**
      * 获取分类下的商品和规格
      */
    public function getGoodsList()
    {

        $category_id = intval(request()->input('category_id'));
        if (empty($category_id)) {
            return $this->errorJson("分类不能为空", []);
        }

        $goods_page = request()->input('goods_page',1);
        $order_filed = request()->input('order_field');
        $order_by = request()->input('order_by','asc');
        $search = request()->input('search');

        $goods_model = $this->getGoodsModel();

        $notDisplay = [
            121, // 话费充值不展示分类里面
            131, // 电费充值不展示分类里面
        ];
        $search = array_filter($search, function ($item) {
            return !empty($item) && $item !== 0  && $item !== "undefined";
        });

        $search['category'] = $category_id;

        $goods_ids = [];
        //重组查询的商品ID
        if (app('plugins')->isEnabled('supplier-local-life') && app('plugins')->isEnabled('supplier')) {
            $goods_ids = \app\common\models\GoodsCategory::select('goods_id')
                ->whereRaw('FIND_IN_SET(?,category_ids)', [$category_id])->pluck('goods_id')->all() ? : [0];

            $goods_ids = \Yunshop\SupplierLocalLife\services\GoodsDecorateService::regroupGoodsComponent($goods_ids,'category');
        }
        $list = $goods_model->searchList($search)
            ->when(!empty($goods_ids),function ($query) use ($goods_ids) {
                $query->whereIn('yz_goods.id', $goods_ids);
            })->uniacid()->WhereInPluginIds()
            ->whereNotIn('plugin_id', $notDisplay)
            ->where('yz_goods.is_hide', 1)
            ->where('yz_goods.status', 1)
            ->select([
                    'yz_goods.id',
                    'yz_goods.title',
                    'yz_goods.thumb',
                    'yz_goods.market_price',
                    'yz_goods.price',
                    'yz_goods.min_price',
                    'yz_goods.max_price',
                    'yz_goods.cost_price',
                    'yz_goods.stock',
                    'yz_goods.real_sales',
                    'yz_goods.show_sales',
                    'yz_goods.virtual_sales',
                    'yz_goods.has_option',
                    'yz_goods.plugin_id',
                    DB::raw('show_sales+virtual_sales as total_sales'),
            ])->with(['hasManySpecs' => function ($query) {
                return $query->select('id', 'goods_id', 'title', 'description')->with([
                    'hasManySpecsItem' => function ($query) {
                        return $query->select('id', 'title', 'specid', 'thumb');
                    }]
                )->orderBy('display_order', 'asc');
            }, 'hasManyOptions' => function ($query) {
                return $query->select('id', 'goods_id', 'title', 'thumb', 'product_price', 'market_price', 'stock', 'specs', 'weight');

            },'hasManyGoodsFilter' => function ($query) {
                return $query->select('goods_id', 'filtering_id');},
            ])
            ->when($order_filed,function ($query) use ($order_filed,$order_by) {
                if ($order_filed == 'show_sales') {
                    $order_filed = 'total_sales';
                    return $query->orderBy($order_filed,$order_by);
                }
                return $query->orderBy('yz_goods.'.$order_filed,$order_by);
            })
            ->orderBy('yz_goods.display_order', 'desc')
            ->orderBy('yz_goods.id', 'desc')
            ->paginate(15, ['*'], 'page', $goods_page);


        $goodsIds = $list->pluck('id')->all();
        $goodsCategory = [];
        $category_to_option_open = \Setting::get('shop.category.category_to_option') ?: 0;
        if ($goodsIds && $category_to_option_open) {
            $goodsCategory = GoodsCategory::select('goods_id', 'goods_option_id')
                ->with('goodsOption')
                ->whereRaw('FIND_IN_SET(?,category_ids)', [$category_id])
                ->whereIn('goods_id', $goodsIds)
                ->orderBy('goods_option_id', 'desc')
                ->groupBy('goods_id')
                ->get()->toArray();
            $goodsCategory = collect($goodsCategory)->keyBy('goods_id')->all();
        }

        $list->map(function (Goods $goodsModel) use ($goodsCategory) {
            //前端需要goods_id
            $goodsModel->goods_id = $goodsModel->id;
            $goodsModel->buyNum = 0;
            $goodsModel->thumb = yz_tomedia($goodsModel->thumb);

            foreach ($goodsModel->hasManySpecs as &$spec) {
                foreach ($spec->hasManySpecsItem as &$specitem) {
                    $specitem->thumb = yz_tomedia($specitem->thumb);
                }
            }

            if ($goodsModel->has_option && $goodsModel->hasManyOptions->isNotEmpty()) {
                $goodsModel->stock = $goodsModel->hasManyOptions->sum('stock');
                foreach ($goodsModel->hasManyOptions as &$item) {
                    $item->thumb = replace_yunshop(yz_tomedia($item->thumb));
                }
            }

            //$goodsService = new \app\frontend\modules\goods\services\GoodsService($goodsModel);
            //$goodsService->priceModule();
            //$goodsService->otherData();

            if ($goodsCategory[$goodsModel->id] && $goodsCategory[$goodsModel->id]['goods_option_id'] && $goodsCategory[$goodsModel->id]['goods_option']) {
                $goodsModel->offsetSet('category_option_id', $goodsCategory[$goodsModel->id]['goods_option_id']);
                $goodsModel->offsetSet(
                    'goods_option_ids',
                    explode('_', $goodsCategory[$goodsModel->id]['goods_option']['specs'])
                );
                $goodsModel->thumb = yz_tomedia(
                    $goodsCategory[$goodsModel->id]['goods_option']['thumb']
                ) ?: $goodsModel->thumb;
            }

            //特殊商品
            if (app('plugins')->isEnabled('special-goods')) {
                $goodsModel->is_special = \Yunshop\SpecialGoods\services\SpecialGoodsShop::isSpecial(
                    $goodsModel->id,
                    $goodsModel->plugin_id
                );
            }

            //通证价
            if (app('plugins')->isEnabled('pass-price') && \Setting::get('pass-price.set.plugin_enable')) {
                $goodsModel->pass_price = [
                    'price' => bcmul($goodsModel->price, \Setting::get('pass-price.set.pass_price'), 2),
                    'name'  => \Setting::get('pass-price.set.diy_name') . '价',
                ];
            }

        });


        $list = $list->toArray();

        //积分商城
        if (app('plugins')->isEnabled('point-mall')) {
            $list['data'] = \Yunshop\PointMall\api\models\PointMallGoodsModel::setPointGoods($list['data']);
        }

        // 爱心值价格显示
        if (app('plugins')->isEnabled('love-price-display')) {
            $list['data'] = \Yunshop\LovePriceDisplay\services\LovePrice::getCategoryData($list['data']);
        }

        return $this->successJson('goods_list', $list);
    }

    protected function getGoodsModel()
    {
        $goods_model = \app\common\modules\shop\ShopConfig::current()->get('goods.models.commodity_classification');
        $goods_model = new $goods_model;
        return $goods_model;
    }


    protected function getAds()
    {
        $slide = Slide::getSlidesIsEnabled()->get();
        if (!$slide->isEmpty()) {
            $slide = $slide->toArray();
            foreach ($slide as &$item) {
                $item['thumb'] = yz_tomedia($item['thumb']);
            }
        }
        return $slide;
    }
}