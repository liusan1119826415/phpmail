<?php

namespace app\frontend\modules\project\services\diymodel;

use app\common\models\goods\GoodsOptionModel;
use app\common\models\GoodsSpecItem;
use app\frontend\models\GoodsOption;
use app\frontend\modules\project\services\related\GoodsOptionQueryService;
use app\frontend\modules\project\services\related\GoodsOptionDataBuilder;
use app\frontend\modules\project\services\related\CurrentGoodsOptionHandler;
use Illuminate\Support\Facades\Cache;
use Yunshop\Supplier\common\models\SupplierColorPlane;

class DesignGoodsService
{
    /**
     * 获取商品数据（含缓存）
     */
    public function getGoodsData(int $goods_id): array
    {
        $cacheKey = "diy_model_goods_data_{$goods_id}";
        $data = Cache::get($cacheKey);
        if ($data) return $data;

        $queryService = new GoodsOptionQueryService();
        $builder = new GoodsOptionDataBuilder($this);
        $handler = new CurrentGoodsOptionHandler($goods_id, $queryService, $builder);
        $data = $handler->fetch();

        Cache::put($cacheKey, $data, 24 * 60 + rand(10, 99));
        return $data;
    }

    /**
     * 获取选项模型数据
     */
    public function getOptionModels($optionIds)
    {
        $models = GoodsOptionModel::select("id", "option_id", "name", "changeLock", "default_color", "select_color", "map_param", "visible", "sort")
            ->whereIn('option_id', $optionIds)
            ->get()
            ->groupBy('option_id');

        // 收集所有颜色ID
        $allColorIds = $models->flatMap(function ($items) {
            return $items->flatMap(function ($item) {
                $colorIds = [];

                $default_color = $this->safeUnserialize($item->default_color);
                $select_color = $this->safeUnserialize($item->select_color);

                // 提取 default_color 的 ID
                if (isset($default_color['id'])) {
                    $colorIds[] = $default_color['id'];
                }

                // 提取 select_color 的 IDs
                if (is_array($select_color)) {
                    foreach ($select_color as $color) {
                        if (isset($color['id'])) {
                            $colorIds[] = $color['id'];
                        }
                    }
                }

                return $colorIds;
            });
        })->unique()->toArray();

        // 一次性查询所有颜色数据
        $colorDataMap = [];
        if (!empty($allColorIds)) {
            $colorDataMap = SupplierColorPlane::select("id", "name", "thumb")
                ->whereIn('id', $allColorIds)
                ->get()
                ->keyBy('id')
                ->toArray();
        }

        // 处理数据
        return $models->map(function ($items) use ($colorDataMap) {
            return $items->sortBy('sort')->map(function ($item) use ($colorDataMap) {
                $default_color = $this->safeUnserialize($item->default_color);
                $select_color = $this->safeUnserialize($item->select_color);
                $item->map_param = $this->safeUnserialize($item->map_param);

                // 处理 default_color
                if (isset($default_color['id']) && isset($colorDataMap[$default_color['id']])) {
                    $colorId = $default_color['id'];
                    $default_color['thumb'] = yz_tomedia($colorDataMap[$colorId]['thumb']);
                    $default_color['name'] = $colorDataMap[$colorId]['name'];
                }

                // 处理 select_color
                if (is_array($select_color)) {
                    foreach ($select_color as &$color) {
                        if (isset($color['id']) && isset($colorDataMap[$color['id']])) {
                            $colorId = $color['id'];
                            $color['thumb'] = yz_tomedia($colorDataMap[$colorId]['thumb']);
                            $color['name'] = $colorDataMap[$colorId]['name'];
                        }
                    }
                    unset($color); // 解除引用
                }
                $item->default_color = $default_color;
                $item->select_color = $select_color;

                return $item;
            });
        })->toArray();
    }

    /**
     * 获取所有选项的父模型数据
     */
    public function getAllOptionParentModels($optionIds, $allOptionModels)
    {
        // 一次性查出所有相关option（兼容多ID拼接）
        $allOptions = GoodsOption::whereIn('id', $optionIds)
            ->with(['beLongsToSupplier' => function ($query) {
                $query->select("id", "store_name");
            }])
            ->get();

        // 构建返回结构
        $parentModels = [];

        $specItemIds = $allOptions->pluck('spec_item_id')->filter()->unique()->toArray();

        // 批量查询GoodsSpecItem数据
        if (!empty($specItemIds)) {
            $specItems = GoodsSpecItem::whereIn('id', $specItemIds)
                ->pluck('productType', 'id')
                ->toArray();
        } else {
            $specItems = [];
        }

        // 按 specs 分组处理
        $specsGroups = [];
        foreach ($allOptions as $option) {
            $specsGroups[$option->specs][] = $option;
        }

        // 处理每个 specs 组
        foreach ($specsGroups as $specsKey => $options) {
            $specModelData = [];

            foreach ($options as $option) {
                $productType = $specItems[$option->spec_item_id] ?? null;
                $optionId = $option->id;
                $model_data = $allOptionModels[$optionId] ?? collect();

                // 构建选项数据
                $specModelData[] = [
                    "id" => $optionId,
                    "goods_id" => $option->goods_id,
                    "d3ModelUrl_weld" => $option->d3ModelUrl_weld ? yz_tomedia($option->d3ModelUrl_weld) : "",
                    "cad_plan_model" => $option->cad_plan_model ? yz_tomedia($option->cad_plan_model) : "",
                    "thumb3dModelUrl" => $option->thumb3dModelUrl ? yz_tomedia($option->thumb3dModelUrl) : "",
                    "thumb" => yz_tomedia($option->thumb),
                    "blockname" => $option->block_name,
                    "price" => $option->product_price,
                    'productType' => $productType,
                    "modelType" => $option->modelType,
                    "single" => $option->singleType,
                    "model_data" => array_values($model_data),
                    "length" => $option->length,
                    "width" => $option->width,
                    "height" => $option->height,
                    "be_longs_to_supplier" => $option->beLongsToSupplier,
                ];
            }

            if (!empty($specModelData)) {
                $parentModels[$specsKey] = $specModelData;
            }
        }

        return $parentModels;
    }

    /**
     * 安全反序列化
     */
    private function safeUnserialize($value)
    {
        return !empty($value) ? @unserialize($value) : [];
    }
}
