<?php 
namespace app\frontend\modules\project\services\related;

use app\common\models\GoodsSpecItem;
use app\common\models\GoodsOption;
use app\frontend\modules\member\models\MemberFavorite;
use app\common\traits\ProcessGoodsOptionTrait;
use app\frontend\modules\project\services\diymodel\DesignGoodsService;

// ---------- 2. 选项数据构建器（将原始选项转换为输出数组） ----------
class GoodsOptionDataBuilder
{

    use ProcessGoodsOptionTrait;
    
    /** @var DesignGoodsService */
    private $goodsService;
    
    private $memberId;

    public function __construct(DesignGoodsService $goodsService, $memberId = null)
    {
        $this->goodsService = $goodsService;
        $this->memberId = $memberId ?? \YunShop::app()->getMemberId();
    }

    /**
     * 批量构建选项的完整数组
     *
     * @param \Illuminate\Support\Collection $optionsCollection
     * @param int $currentGoodsId 当前商品ID，用于判断 current_goods_id
     * @param array $relatedOptionIds 关联选项ID列表
     * @param bool $markAsRelated 是否标记为关联选项（为 true 时给每个选项添加 is_related 属性）
     * @return array
     */
    public function build(
        \Illuminate\Support\Collection $optionsCollection,
        int $currentGoodsId,
        array $relatedOptionIds = [],
        bool $markAsRelated = false
    ): array {
        if ($optionsCollection->isEmpty()) {
            return [];
        }

        // 1. 补全缺失的供应商关系（如果未加载）
        if (!$optionsCollection->first()->relationLoaded('beLongsToSupplier')) {
            $optionsCollection->load('beLongsToSupplier:id,store_name');
        }

        // 2. 批量查询规格项 productType
        $specItemIds = $optionsCollection->pluck('spec_item_id')->filter()->unique()->toArray();
        $specItems = [];
        if (!empty($specItemIds)) {
            $specItems = GoodsSpecItem::whereIn('id', $specItemIds)->pluck('productType', 'id')->toArray();
        }

        // 3. 获取所有选项的模型参数
        $optionIds = $optionsCollection->pluck('id')->toArray();
        $modelParams = $this->goodsService->getOptionModels($optionIds);

        // 4. 构建父模型数据（基于 specs 分组）
        $parentModels = $this->buildParentModels($optionsCollection, $modelParams, $specItems, $relatedOptionIds, $currentGoodsId);

        // 5. 转换每个选项为数组并填充附加字段
        $data = [];
        foreach ($optionsCollection as $option) {
            $item = $option->toArray();

            // 分类信息
            $category_id = $category_name = null;
            if ($option->specCategory->isNotEmpty()) {
                $first = $option->specCategory->first();
                if ($first && $first->category) {
                    $category_id   = $first->category->id;
                    $category_name = $first->category->name;
                }
            }
            $item['category_id']   = $category_id;
            $item['category_name'] = $category_name;

            // 模型数据
            $model_data = $modelParams[$option->id] ?? collect();
            $item['model_data']         = array_values($model_data);
            $item['model_chilren_data'] = $parentModels[$option->specs] ?? [];

            // 多媒体
            $item['thumb']          = yz_tomedia($item['thumb'] ?? '');
            $item['cad_plan_model'] = yz_tomedia($item['cad_plan_model'] ?? '');
            $item['d3ModelUrl_weld'] = yz_tomedia($item['d3ModelUrl_weld'] ?? '');
            $item['thumb3dModelUrl'] = yz_tomedia($item['thumb3dModelUrl'] ?? '');

            // 收藏状态
            $mirror_type = $this->getMirrorType($option->id, 0);
            $collect = MemberFavorite::getFavoriteByOptionId($option->id, $this->memberId, $mirror_type);
            $item['is_favorite'] = [
                'status'  => $collect ? 1 : 0,
                'message' => $collect ? '商品已收藏' : '商品未收藏'
            ];

            // 商品信息合并
            $goodsTitle = $option->goods['title'] ?? '';
            $parts      = explode("+", $item['title'] ?? '');
            $item['title']      = $goodsTitle . ($parts[0] ?? '');
            $item['option_last_title'] = end($parts);
            $item['price']      = ceil($item['product_price']);
            $item['lead_time']  = $option->goods['lead_time'] ?? '';
            $item['sku']        = $option->goods['sku'] ?? '';
            $item['status']     = $option->goods['status'];
            $item['mirror_enable'] = $item['mirror_enable'] ?? 0;
            $item['productType']   = $specItems[$option->spec_item_id] ?? '';
            if ($option->goods_id == $currentGoodsId) {
                $item['current_goods_id'] = $option->goods_id;
            }

            // modelSlim 反序列化
            $item['modelSlim'] = isset($item['modelSlim']) && $item['modelSlim']
                ? unserialize($item['modelSlim']) : [];

            unset($item['goods']);

            if ($markAsRelated) {
                $item['is_related'] = true;
            }

            $data[] = $item;
        }

        return $data;
    }

    /**
     * 构建父模型数据（按 specs 分组，含关联选项扩展查询）
     */
    private function buildParentModels(
        \Illuminate\Support\Collection $optionsCollection,
        array $modelParams,
        array $specItems,
        array $relatedOptionIds = [],
        int $goodsId = 0
    ): array {

       
        // 关联选项扩展查询逻辑（与 getAllParentDiyModelsByOptions 一致）
        $specItemIdsV1 = [];
        if (!empty($relatedOptionIds)) {
            $specItemIdsV1 = GoodsOption::whereIn('id', $relatedOptionIds)->pluck('spec_item_id')->toArray();
           
        }

        $optionIdsV1 = $optionsCollection->pluck('id')->toArray();
        $oldOptionIds = GoodsOption::where('goods_id', $goodsId)->pluck('id')->toArray();
        $expandedOptionIds = array_unique(array_merge($optionIdsV1, $oldOptionIds));

         $optionsCollection = GoodsOption::where(function ($query) use ($expandedOptionIds, $specItemIdsV1) {
                $query->whereIn('id', $expandedOptionIds);
                if (!empty($specItemIdsV1)) {
                    $query->orWhereIn('spec_item_id', $specItemIdsV1);
                }
            })->get();

            // 重新查询模型参数
            $optionIds = $optionsCollection->pluck('id')->toArray();
            $modelParams = $this->goodsService->getOptionModels($optionIds);

            // 重新查询规格项
            $specItemIds = $optionsCollection->pluck('spec_item_id')->filter()->unique()->toArray();
            if (!empty($specItemIds)) {
                $specItems = GoodsSpecItem::whereIn('id', $specItemIds)->pluck('productType', 'id')->toArray();
            }

        $specsGroups = [];
        foreach ($optionsCollection as $option) {
            $specsGroups[$option->specs][] = $option;
        }

        $parentModels = [];
        foreach ($specsGroups as $specsKey => $options) {
            $specModelData = [];
            foreach ($options as $option) {
                $productType = $specItems[$option->spec_item_id] ?? null;
                $model_data  = $modelParams[$option->id] ?? collect();

                $specModelData[] = [
                    "id"                => $option->id,
                    "goods_id"          => $option->goods_id,
                    "d3ModelUrl_weld"   => yz_tomedia($option->d3ModelUrl_weld ?? ""),
                    "cad_plan_model"    => yz_tomedia($option->cad_plan_model ?? ""),
                    "thumb3dModelUrl"   => yz_tomedia($option->thumb3dModelUrl ?? ""),
                    "thumb"             => yz_tomedia($option->thumb),
                    "blockname"         => $option->block_name,
                    "price"             => $option->product_price,
                    'productType'       => $productType,
                    "modelType"         => $option->modelType,
                    "single"            => $option->singleType,
                    "model_data"        => array_values($model_data),
                    "length"            => $option->length,
                    "width"             => $option->width,
                    "height"            => $option->height,
                    "be_longs_to_supplier" => $option->beLongsToSupplier,
                ];
            }
            if (!empty($specModelData)) {
                $parentModels[$specsKey] = $specModelData;
            }
        }
        return $parentModels;
    }

 
}