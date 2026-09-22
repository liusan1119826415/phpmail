<?php

namespace app\frontend\modules\project\services;

use app\common\models\Goods;
use app\common\models\GoodsOption;
use app\common\models\goods\GoodsSpecCategory;
use app\common\models\MemberCart;
use app\common\models\MemberFavorite;
use app\common\models\goods\GoodsOptionClick;
use app\common\models\OrderGoods;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class RecommendGoodsService
{
    /**
     * 推荐商品数量
     */
    const RECOMMEND_COUNT = 10;

    /**
     * 获取猜你喜欢商品
     * @param int $memberId 会员ID
     * @param int $currentGoodsId 当前商品ID
     * @return array
     */
    public function getRecommendGoods($memberId, $currentGoodsId, $excludeOptionIds = [])
    {
        // 获取所有可能的推荐源分类ID
        $categoryIds = $this->getAllCandidateCategoryIds($memberId, $currentGoodsId);

        // 如果没有找到任何分类，使用当前商品分类
        if (empty($categoryIds)) {
            $categoryIds = $this->getCurrentGoodsCategoryIds($currentGoodsId);
        }

        // 如果还是没有分类，返回空数组
        if (empty($categoryIds)) {
            return [];
        }

        // 根据分类ID批量获取推荐商品（传入排除ID）
        $recommendGoods = $this->getRecommendGoodsByCategories($categoryIds, $currentGoodsId, $excludeOptionIds);

        // 如果推荐商品不足，用热门商品补充（同样传入排除ID，并合并已推荐的ID）
        if (count($recommendGoods) < self::RECOMMEND_COUNT) {
            $recommendGoods = $this->supplementWithHotGoods($recommendGoods, $currentGoodsId, $excludeOptionIds);
        }

        // 降级逻辑：如果排除后没有任何商品，说明已全部轮播完，去掉排除条件重新随机取
        if (empty($recommendGoods)) {
            $recommendGoods = $this->getRecommendGoods($memberId, $currentGoodsId, []);
        }

        return $recommendGoods;
    }

    /**
     * 获取所有可能的推荐源分类ID（综合所有情况）
     */
    private function getAllCandidateCategoryIds($memberId, $currentGoodsId)
    {
        $allOptionIds = [];

        // 1. 购物车商品option_id
        $cartOptionIds = MemberCart::where('member_id', $memberId)
            ->where('goods_id', '!=', $currentGoodsId)
            ->where('option_id', '>', 0)
            ->select('option_id')
            ->pluck('option_id')
            ->toArray();
        $allOptionIds = array_merge($allOptionIds, $cartOptionIds);

        // 2. 浏览记录option_id
        $clickOptionIds = GoodsOptionClick::where('member_id', $memberId)
            ->where('goods_id', '!=', $currentGoodsId)
            ->where('option_id', '>', 0)
            ->select('option_id')
            ->pluck('option_id')
            ->toArray();
        $allOptionIds = array_merge($allOptionIds, $clickOptionIds);

        // 3. 收藏记录option_id
        $favoriteOptionIds = MemberFavorite::where('member_id', $memberId)
            ->where('goods_id', '!=', $currentGoodsId)
            ->where('option_id', '>', 0)
            ->select('option_id')
            ->pluck('option_id')
            ->toArray();
        $allOptionIds = array_merge($allOptionIds, $favoriteOptionIds);

        // 4. 购买记录option_id
        $orderOptionIds = OrderGoods::where('uid', $memberId)
            ->where('goods_id', '!=', $currentGoodsId)
            ->where('goods_option_id', '>', 0)
            ->select('goods_option_id')
            ->pluck('goods_option_id')
            ->toArray();
        $allOptionIds = array_merge($allOptionIds, $orderOptionIds);

        // 去重option_id
        $allOptionIds = array_unique($allOptionIds);

        if (empty($allOptionIds)) {
            return [];
        }

        // 批量获取这些option_id对应的分类ID
        return $this->getCategoriesFromOptions($allOptionIds);
    }

    /**
     * 获取当前商品分类ID
     */
    private function getCurrentGoodsCategoryIds($currentGoodsId)
    {
        $currentOption = GoodsOption::where('goods_id', $currentGoodsId)
            ->where('spec_item_id', '>', 0)
            ->pluck('id')->toArray();

        if (empty($currentOption)) {
            return [];
        }

        return $this->getCategoriesFromOptions($currentOption);
    }

    /**
     * 根据option_ids获取分类ID
     */
    private function getCategoriesFromOptions($optionIds)
    {
        if (empty($optionIds)) {
            return [];
        }

        // 通过GoodsOption关联GoodsSpecCategory获取二级分类ID
        $categories = GoodsOption::whereIn('yz_goods_option.id', $optionIds)
            ->join('yz_goods_spec_category', 'yz_goods_option.spec_item_id', '=', 'yz_goods_spec_category.spec_item_id')
            ->where('yz_goods_spec_category.category_type', 2)
            ->select('yz_goods_spec_category.category_id')
            ->distinct()
            ->pluck('category_id')
            ->toArray();

        return $categories;
    }


    /**
     * 根据分类IDs批量获取推荐商品 - 完全使用LEFT JOIN
     */
    private function getRecommendGoodsByCategories($categoryIds, $currentGoodsId, $excludeOptionIds = [])
    {
        $query = GoodsOption::where('yz_goods_option.goods_id', '!=', $currentGoodsId)
            ->where('yz_goods_option.is_default', 1)
            ->leftJoin('yz_goods_spec_category', function ($join) {
                $join->on('yz_goods_option.spec_item_id', '=', 'yz_goods_spec_category.spec_item_id')
                    ->where('yz_goods_spec_category.category_type', 2);
            })
            ->leftJoin('yz_goods', function ($join) {
                $join->on('yz_goods_option.goods_id', '=', 'yz_goods.id')
                    ->where('yz_goods.status', 1)
                    ->where('yz_goods.type2', 1)
                    ->whereNull('yz_goods.deleted_at');
            })
            ->whereIn('yz_goods_spec_category.category_id', $categoryIds)
            ->whereNotNull('yz_goods.id');

        // 排除已展示过的 option_id
        if (!empty($excludeOptionIds)) {
            $query->whereNotIn('yz_goods_option.id', $excludeOptionIds);
        }

        $allOptions = $query->select(
            'yz_goods_option.id',
            'yz_goods_option.thumb',
            'yz_goods_option.product_price as price',
            'yz_goods_option.title',
            'yz_goods_option.is_hot',
            'yz_goods_option.is_discount',
            'yz_goods.id as goods_id',
            'yz_goods.title as goods_title',
            'yz_goods.is_stock'
        )
            ->inRandomOrder()
            ->limit(self::RECOMMEND_COUNT)
            ->get();

        if ($allOptions->isEmpty()) {
            return [];
        }

        // 直接转换结果
        $recommendGoods = [];
        foreach ($allOptions as $option) {
            $recommendGoods[] = [
                'id' => $option->id,
                'goods_id' => $option->goods_id,
                'title' => $option->goods_title . explode("+", $option->title)[0],
                'thumb' => yz_tomedia($option->thumb),
                'price' => $option->price,
                'min_price' => $option->price,
                'max_price' => $option->price,
                "is_discount" => $option->is_discount,
                "is_hot" => $option->is_hot,
                "is_stock" => $option->is_stock

            ];
        }

        return $recommendGoods;
    }


    /**
     * 用热门商品补充推荐列表 - 随机获取
     */
    private function supplementWithHotGoods($currentRecommend, $currentGoodsId, $excludeOptionIds, $limit = null)
    {
        // 如果没有指定limit，计算需要补充的数量
        if ($limit === null) {
            $limit = self::RECOMMEND_COUNT - count($currentRecommend);
        }

        // 如果不需要补充，直接返回原数组
        if ($limit <= 0) {
            return $currentRecommend;
        }

        // 获取已推荐的商品ID，避免重复
        //$recommendedGoodsIds = array_column($currentRecommend, 'goods_id');

        // 已推荐的 option_id + 外部传入的排除ID
        $recommendedOptionIds = array_column($currentRecommend, 'id');
        $allExcludeIds = array_unique(array_merge($recommendedOptionIds, $excludeOptionIds));

        // 随机获取热门商品
        $query = GoodsOption::from('yz_goods_option as go')
            ->select([
                'go.id as option_id',
                'go.goods_id',
                'go.title as option_title',
                'go.product_price as price',
                'go.show_sales as sales',
                'go.thumb',
                'go.is_hot',
                'go.is_discount',
                'g.is_stock',
                'g.id as goods_id',
                'g.title as goods_title',
                'g.status as goods_status',
            ])
            ->join('yz_goods as g', 'go.goods_id', '=', 'g.id')
            ->where('go.is_default', '=', 1)
            ->where('go.goods_id', '!=', $currentGoodsId)
            ->whereNotIn('go.id', $allExcludeIds)  // 排除所有已展示 option_id
            ->where('g.status', 1)
            ->where('g.type2', 1)
            ->whereNull('g.deleted_at')
            ->inRandomOrder() // 随机排序
            ->limit($limit)
            ->get();

        // 转换结果格式，与getRecommendGoodsByCategories保持一致
        foreach ($query as $option) {
            // 处理标题：如果有option_title，用option_title覆盖goods_title
            $title = $option->goods_title;
            if (!empty($option->option_title)) {
                $title = explode("+", $option->option_title)[0];
            }

            $currentRecommend[] = [
                'id' => $option->option_id,
                'goods_id' => $option->goods_id,
                'title' => $title,
                'thumb' => yz_tomedia($option->thumb),
                'price' => $option->price,
                'min_price' => $option->price,
                'max_price' => $option->price,
                'is_discount' => $option->is_discount,
                'is_hot' => $option->is_hot,
                'is_stock' => $option->is_stock,
            ];
        }

        return $currentRecommend;
    }
}
