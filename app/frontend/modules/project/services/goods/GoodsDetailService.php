<?php

namespace app\frontend\modules\project\services\goods;

use app\common\exceptions\ShopException;
use app\common\models\goods\GoodsOptionModel;
use app\common\models\goods\GoodsRelation;
use app\common\models\GoodsSpecItem;
use app\frontend\models\GoodsOption;
use app\frontend\modules\coupon\models\Goods;
use app\frontend\modules\project\services\GoodsBaseService;
use app\common\traits\ProcessGoodsOptionTrait;

class GoodsDetailService
{
    use ProcessGoodsOptionTrait;

    //编辑产品
    public function getGoodsOption(int $goods_id): array
    {
        $startTime = microtime(true);
        $timings = [];

        // ========== 1. 查询商品基本信息（不变） ==========
        $sectionStart = microtime(true);
        $option_select = 'id,goods_id,title,thumb,product_price,cost_price,market_price,stock,specs,weight,product_sn,productType,length,width,height,mirror_enable';
        $goods = Goods::select("id", "title", "structure")->with([
            'hasManySpecs' => function ($query) {
                return $query->select('id', 'goods_id', 'title', 'description')
                    ->with(['hasManySpecsItem' => function ($specs) {
                        return $specs->select('id', 'title', 'specid', 'thumb', 'parent_id', 'level')
                            ->where('show', 1)
                            ->orderBy('display_order', 'asc');
                    }])
                    ->orderBy('display_order', 'asc');
            },
            'hasManyOptions' => function ($query) use ($option_select) {
                return $query->selectRaw($option_select)
                    ->where(function ($q) {
                        $q->where('productType', '!=', 4)
                            ->where('modelType', 0);
                    })
                    ->orWhere(function ($q) {
                        $q->where('productType', 4)
                            ->whereIn('modelType', [0, 1, 2]);
                    });
            }
        ])->find($goods_id);

        if (!$goods) {
            throw new ShopException("未找到该商品");
        }
        $timings['query_goods'] = round((microtime(true) - $sectionStart) * 1000, 2) . 'ms';

        // ========== 新增：获取关联选项ID ==========
        $sectionStart = microtime(true);
        $relatedOptionIds = GoodsRelation::where('goods_id', $goods_id)
            ->pluck('related_goods_id')
            ->unique()
            ->toArray();
        $timings['get_related_ids'] = round((microtime(true) - $sectionStart) * 1000, 2) . 'ms';

        // ========== 新增：合并关联选项到 hasManyOptions ==========
        $specItemIds = [];
        if (!empty($relatedOptionIds)) {
            $sectionStart = microtime(true);

            // 查询关联选项（含其模型关联）
            $specItemIds = GoodsOption::whereIn('id', $relatedOptionIds)->pluck('spec_item_id')->toArray();
            $relatedOptions = GoodsOption::whereIn('spec_item_id', $specItemIds)
                ->selectRaw($option_select)
                ->with(['hasManyOptionModels' => function ($q) {
                    $q->orderBy('sort');
                }])
                ->get();

            // 标记关联来源
            foreach ($relatedOptions as $opt) {
                $opt->is_related = true;
            }

            // 合并去重
            $existingIds = $goods->hasManyOptions->pluck('id')->all();
            foreach ($relatedOptions as $ro) {
                if (!in_array($ro->id, $existingIds)) {
                    $goods->hasManyOptions->push($ro);
                }
            }

            $timings['merge_related_options'] = round((microtime(true) - $sectionStart) * 1000, 2) . 'ms';
        }

        // ========== 2. 处理规格树（原有逻辑，稍后合并关联项） ==========
        $sectionStart = microtime(true);
        $goods->hasManySpecs->each(function ($specs) {
            $items = $specs->hasManySpecsItem
                ->map(function ($item) {
                    return tap($item, function ($item) {
                        $item->thumb = yz_tomedia($item->thumb);
                        $item->children = collect();
                    });
                })
                ->keyBy('id');

            $specs->tree = $items->filter(function ($item) use ($items) {
                if ($item->parent_id > 0 && $parent = $items->get($item->parent_id)) {
                    $parent->children->push($item);
                    return false;
                }
                return true;
            })->values();

            unset($specs->hasManySpecsItem);
        });
        $timings['process_specs_base'] = round((microtime(true) - $sectionStart) * 1000, 2) . 'ms';

        // ========== 新增：将关联选项的规格项合并到第一个规格的 tree 中 ==========
        if (!empty($relatedOptionIds)) {
            $sectionStart = microtime(true);

            // 获取关联选项的 spec_item_id
            $relatedSpecItemIds = GoodsOption::whereIn('id', $relatedOptionIds)
                ->pluck('spec_item_id')
                ->filter()
                ->unique()
                ->toArray();


            $allRelatedIds = $relatedSpecItemIds;   // 假设 $relatedSpecItemIds 已存在（包含初始 ID）

            if (!empty($allRelatedIds)) {
                $parentIds = $allRelatedIds;        // 当前层级待查的父级 ID
                // 递归获取所有子级 ID（支持多级）
                while (!empty($parentIds)) {
                    $childrenIds = GoodsSpecItem::whereIn('parent_id', $parentIds)
                        ->pluck('id')
                        ->toArray();
                    if (empty($childrenIds)) {
                        break;
                    }
                    $allRelatedIds = array_merge($allRelatedIds, $childrenIds);
                    $parentIds = $childrenIds;      // 继续向更深层查找
                }
                $allRelatedIds = array_unique($allRelatedIds);
            } else {
                // 如果没有初始 ID，则查询所有（保持原有降级逻辑）
                $allRelatedIds = [];
            }

            if (!empty($relatedSpecItemIds)) {
                // 查询所有相关规格项（自身 + 所有后代）
                $relatedSpecItems = GoodsSpecItem::select('id', 'title', 'specid', 'thumb', 'parent_id', 'level')
                    ->whereIn('id', $allRelatedIds)
                    ->orderBy('display_order', 'asc')
                    ->get()
                    ->map(function ($item) {
                        $item->thumb = yz_tomedia($item->thumb);
                        $item->children = collect();
                        return $item;
                    });

                // 构建树形结构（与原逻辑完全一致）
                $itemsMap = $relatedSpecItems->keyBy('id');
                $relatedRoots = collect();
                foreach ($relatedSpecItems as $item) {
                    if ($item->parent_id > 0 && isset($itemsMap[$item->parent_id])) {
                        $itemsMap[$item->parent_id]->children->push($item);
                    } else {
                        $relatedRoots->push($item);
                    }
                }

                // 合并到第一个规格的 tree
                $firstSpec = $goods->hasManySpecs->first();
                if ($firstSpec && $relatedRoots->isNotEmpty()) {
                    $existingIds = $firstSpec->tree->pluck('id')->toArray();
                    foreach ($relatedRoots as $rootItem) {
                        if (!in_array($rootItem->id, $existingIds)) {
                            $firstSpec->tree->push($rootItem);
                            $existingIds[] = $rootItem->id;
                        }
                    }
                }
            }

            $timings['merge_spec_items'] = round((microtime(true) - $sectionStart) * 1000, 2) . 'ms';
        }

        // ========== 3. 获取所有选项ID（含关联） ==========
        $optionIds = $goods->hasManyOptions->pluck('id')->toArray();



        // ========== 4. 批量查询选项模型 ==========
        $sectionStart = microtime(true);
        $modelParams = GoodsOptionModel::whereIn('option_id', $optionIds)->get();

        // ========== 【新增】对每个模型进行反序列化处理 ==========
        $sectionStart = microtime(true);
        $modelParams->transform(function ($model) {
            // 批量反序列化字段（参考 getOptionModelsOptimized 的做法）
            $model->default_color = $this->safeUnserialize($model->default_color);
            $model->select_color  = $this->safeUnserialize($model->select_color);

            $model->map_param     = $this->safeUnserialize($model->map_param);
            $model->meshs_name    = $this->safeUnserialize($model->meshs_name);
            return $model;
        });


        $timings['query_option_models'] = round((microtime(true) - $sectionStart) * 1000, 2) . 'ms';

        // ========== 5. 按 option_id 分组 ==========
        $modelParamsGrouped = $modelParams->groupBy('option_id');

        // ========== 6. 批量处理所有选项的 thumb ==========
        $sectionStart = microtime(true);
        $thumbUrls = [];
        foreach ($goods->hasManyOptions as $item) {
            if (!empty($item->thumb)) {
                $thumbUrls["thumb_{$item->id}"] = $item->thumb;
            }
        }
        $processedThumbs = [];
        foreach ($thumbUrls as $key => $thumb) {
            $processedThumbs[$key] = yz_tomedia($thumb);
        }
        foreach ($goods->hasManyOptions as $item) {
            $item->thumb = $processedThumbs["thumb_{$item->id}"] ?? '';
        }
        $timings['process_thumbs'] = round((microtime(true) - $sectionStart) * 1000, 2) . 'ms';

        // ========== 7. 批量处理所有选项模型 ==========
        $sectionStart = microtime(true);
        $allModelsToProcess = collect();
        foreach ($modelParamsGrouped as $optionId => $models) {
            foreach ($models as $model) {
                $model->temp_option_id = $optionId;
                $allModelsToProcess->push($model);
            }
        }
        $processedModels = [];
        if ($allModelsToProcess->isNotEmpty()) {
            $processedResults = $this->processGoodsOptionBatch($allModelsToProcess);
            foreach ($processedResults as $result) {
                $optionId = $result->temp_option_id;
                if (!isset($processedModels[$optionId])) {
                    $processedModels[$optionId] = [];
                }
                $processedModels[$optionId][] = $result;
            }
        }




        $timings['batch_process_option_models'] = round((microtime(true) - $sectionStart) * 1000, 2) . 'ms';

        // ========== 8. 赋值给选项 ==========
        $sectionStart = microtime(true);
        foreach ($goods->hasManyOptions as &$item) {
            $item->has_many_option_models = $processedModels[$item->id] ?? [];
        }


        $timings['assign_to_options'] = round((microtime(true) - $sectionStart) * 1000, 2) . 'ms';

        // ========== 9. 获取3D模型数据（GoodsBaseService 已包含关联处理） ==========
        $sectionStart = microtime(true);
        $goodsBaseService = new GoodsBaseService($goods->id);
        $goods->three_model = $goodsBaseService->getThreeModel();
        $timings['get_three_model'] = round((microtime(true) - $sectionStart) * 1000, 2) . 'ms';

        // ========== 日志 ==========
        $totalTime = round((microtime(true) - $startTime) * 1000, 2);
        $timings['TOTAL'] = $totalTime . 'ms';
        \Log::debug('getGoodsOption 详细执行时间', [
            'goods_id' => $goods_id,
            'timings' => $timings,
            'option_count' => count($optionIds),
            'model_count' => $allModelsToProcess->count(),
            'related_count' => count($relatedOptionIds)
        ]);


        $result = $goods->toArray();

        if (!empty($specItemIds) && isset($result['has_many_options'])) {
            foreach ($result['has_many_options'] as &$option) {

                foreach ($option['has_many_option_models'] as &$model) {
                    // 使用已修复的 safeUnserialize，兼容已经是数组的情况
                    $model['default_color'] = $this->safeUnserialize($model['default_color'] ?? null);
                    $model['select_color']  = $this->safeUnserialize($model['select_color'] ?? null);
                    $model['map_param']     = $this->safeUnserialize($model['map_param'] ?? null);
                    $model['meshs_name']    = $this->safeUnserialize($model['meshs_name'] ?? null);
                }
            }
        }

        return $result;
    }
}
