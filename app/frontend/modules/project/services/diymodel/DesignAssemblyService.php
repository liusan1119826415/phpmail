<?php

namespace app\frontend\modules\project\services\diymodel;

use app\common\exceptions\AppException;
use app\frontend\modules\coupon\models\Goods;
use app\frontend\models\GoodsOption;
use app\common\models\project\CloudDesign;
use app\common\models\project\Project;
use app\common\models\project\Floors;
use app\common\models\project\FloorSpace;
use app\common\models\MemberCart;
use app\frontend\modules\project\services\AssemblyGoodsService;
use app\frontend\modules\project\services\GoodsComparator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class DesignAssemblyService
{
    const FREECOM = 5; //自由组合

    /**
     * 处理design_data，创建或更新楼层、空间和购物车商品
     */
    public function handleDesignData(array $designData, int $projectId, array $old_design_data): void
    {
        if (empty($designData['floors'])) {
            return;
        }
        $member_id = \YunShop::app()->getMemberId();
        //判断当前项目是否激活
        $project = Project::find($projectId);
        $activate = $project->activate;
        $spaceIds = FloorSpace::where('project_id', $projectId)->pluck("id")->toArray();
        foreach ($spaceIds as $space_id) {
            $cacheKey = "member_cart_list_{$member_id}_{$space_id}";
            Cache::forget($cacheKey);
        }
        $goodsIds = MemberCart::where('project_id', $projectId)->where('type', 2)->pluck("goods_id")->toArray();

        MemberCart::where('project_id', $projectId)->forceDelete();
        Goods::whereIn('id', $goodsIds)
            ->forceDelete();
        GoodsOption::whereIn('goods_id', $goodsIds)
            ->delete();

        Redis::del("user:{$member_id}:active_project_1");

        // 处理每个楼层
        $this->processFloors($projectId, $designData['floors']);
        foreach ($designData['floors'] as $floorIndex => $floorData) {
            // 创建或更新楼层
            $floor = $this->updateOrCreateFloor($projectId, $floorData, $floorIndex, $activate);

            // 处理每个空间
            if (!empty($floorData['spaces'])) {
                $this->processSpaces($projectId, $floorData['spaces'], $floor->id);
                foreach ($floorData['spaces'] as $spaceIndex => $spaceData) {
                    // 创建或更新空间
                    $space = $this->updateOrCreateSpace($projectId, $floor->id, $spaceData, $spaceIndex, $activate);

                    // 处理商品列表
                    if (!empty($spaceData['goodsList'])) {
                        $this->handleGoodsList($projectId, $floor->id, $space->id, $spaceData['goodsList'], $old_design_data);
                    }
                }
            }
        }
    }

    private function processFloors(int $projectId, array $floorsData)
    {
        $importedUuids = collect($floorsData)->pluck('uuid')->filter()->toArray();

        // 删除不在本次导入中的楼层（软删除或硬删除）
        Floors::where('project_id', $projectId)
            ->where(function ($query) use ($importedUuids) {
                if (empty($importedUuids)) {
                    $query->whereNotNull('uuid');
                } else {
                    $query->whereNotIn('uuid', $importedUuids)
                        ->orWhereNull('uuid');
                }
            })->delete();
    }

    private function processSpaces(int $projectId, array $spacesData, int $floorId)
    {
        $importedUuids = collect($spacesData)->pluck('uuid')->filter()->toArray();

        FloorSpace::where('project_id', $projectId)->where('floor_id', $floorId)
            ->where(function ($query) use ($importedUuids) {
                if (empty($importedUuids)) {
                    $query->whereNotNull('uuid');
                } else {
                    $query->whereNotIn('uuid', $importedUuids)
                        ->orWhereNull('uuid');
                }
            })
            ->delete();
    }

    /**
     * 创建或更新楼层
     */
    protected function updateOrCreateFloor(int $projectId, array $floorData, int $floorIndex, int $activate): Floors
    {
        $uuid = $floorData['uuid'] ?? null;
        $floor = Floors::where('uuid', $uuid)->where('project_id', $projectId)->first();
        if ($floor) {
            $floor->name = $floorData['name'] ?? "楼层" . ($floorIndex + 1);
            $floor->sort = $floorIndex;
            $floor->save();
        } else {
            $floor = new Floors();
            $floor->project_id = $projectId;
            if ($activate) {
                $floor->activate = $floorIndex == 0 ? 1 : 0;
            }
            $floor->uuid = $uuid;
            $floor->name = $floorData['name'] ?? "楼层" . ($floorIndex + 1);
            $floor->sort = $floorIndex;
            $floor->save();
        }

        return $floor;
    }

    /**
     * 创建或更新空间
     */
    protected function updateOrCreateSpace(int $projectId, int $floorId, array $spaceData, int $spaceIndex, int $activate): FloorSpace
    {
        $spaceId = $spaceData['uuid'] ?? null;
        $space = FloorSpace::where('uuid', $spaceId)->where('project_id', $projectId)->first();

        if ($space) {
            $space->name = $spaceData['name'] ?? "空间" . ($spaceIndex + 1);
            $space->sort = $spaceIndex;
            $space->save();
        } else {
            $space = new FloorSpace();
            $space->project_id = $projectId;
            $space->floor_id = $floorId;
            if ($activate) {
                $space->activate = $spaceIndex == 0 ? 1 : 0;
            }
            $space->name = $spaceData['name'] ?? "空间" . ($spaceIndex + 1);
            $space->uuid = $spaceId;
            $space->sort = $spaceIndex;
            $space->save();
        }

        return $space;
    }

    /**
     * 处理商品列表
     */
    protected function handleGoodsList(int $projectId, int $floorId, int $spaceId, array $goodsList, array $old_design_data): void
    {
        $insertQueue = [];

        // 获取自由组合的商品统计
        $groupedStats = $this->processGroupGoods($goodsList);

        if ($groupedStats) {
            foreach ($groupedStats as $groupedStat) {
                $indices = $groupedStat['goods_indices'] ?? [];

                if (!empty($indices)) {
                    $minIndex = min($indices);

                    $insertQueue[$minIndex] = [
                        'type' => 'freecom',
                        'data' => $groupedStat,
                        'processed' => false,
                        'all_indices' => $indices
                    ];
                }
            }
        }

        // 处理拼接商品
        $comparator = new GoodsComparator();
        $groupGoodsList = $comparator->compareGoods($goodsList);

        foreach ($groupGoodsList as $gData) {
            $goodsData = $gData['sample_data'];
            $goods_count = $gData['count'];

            if (!empty($goodsData['data']) && $goodsData['data']['productType'] != self::FREECOM) {
                $indices = $gData['goods_indices'] ?? [];

                if (!empty($indices)) {
                    $minIndex = min($indices);

                    $insertQueue[$minIndex] = [
                        'type' => 'combined',
                        'data' => [
                            'goodsData' => $goodsData,
                            'goods_count' => $goods_count,
                            'groupData' => $gData
                        ],
                        'processed' => false,
                        'all_indices' => $indices
                    ];
                }
            }
        }

        ksort($insertQueue);

        $maxSort = 0;
        $sort = $maxSort + 1;

        foreach ($insertQueue as $minIndex => $item) {
            if ($item['type'] == 'freecom') {
                $this->processSingleGoods(
                    $projectId,
                    $floorId,
                    $spaceId,
                    $item['data'],
                    $sort
                );
            } else {
                $this->processCombinedGoods(
                    $projectId,
                    $floorId,
                    $spaceId,
                    $item['data']['goodsData'],
                    $old_design_data,
                    $item['data']['goods_count'],
                    $sort
                );
            }
            $sort++;
        }
    }

    /**
     * 分组统计商品
     */
    private function processGroupGoods($goodsList)
    {
        $groupedStats = collect($goodsList)
            ->filter(function ($item) {
                return (isset($item['data']['productType']) && $item['data']['productType'] == 5) ||
                    (isset($item['children']) && count($item['children']) == 1);
            })
            ->groupBy(function ($item) {
                $scale = $item['scale'] ?? ['x' => 1, 'y' => 1, 'z' => 1];
                $isMirror = ($scale['x'] * $scale['z'] < 0) ? '_mirror' : '_normal';

                $isCustomized = isset($item['customized']['is_customized']) && $item['customized']['is_customized'] === true
                    && isset($item['data']['productType']) && $item['data']['productType'] == 5;

                if ($isCustomized) {
                    $remark = $item['customized']['customized_remark'] ?? '';
                    $remarkKey = preg_replace('/\s+/u', '', $remark);
                    return $item['data']['goods_id'] . '_custom_' . $remarkKey . $isMirror;
                } else {
                    return $item['data']['goods_id'] . '_' .
                        $item['data']['id'] . '_' .
                        $item['data']['productType'] . $isMirror;
                }
            })
            ->map(function ($group, $key) use ($goodsList) {
                $firstItem = $group->first();
                $scale = $firstItem['scale'] ?? ['x' => 1, 'y' => 1, 'z' => 1];
                $isMirrored = ($scale['x'] * $scale['z'] < 0);

                $indices = [];
                foreach ($group as $item) {
                    foreach ($goodsList as $index => $originalItem) {
                        if (
                            isset($originalItem['goods_uuid']) && isset($item['goods_uuid']) &&
                            $originalItem['goods_uuid'] == $item['goods_uuid']
                        ) {
                            $indices[] = $index;
                            break;
                        }
                    }
                }

                return [
                    'goods_id' => $firstItem['data']['goods_id'],
                    'option_id' => $firstItem['data']['id'],
                    'productType' => $firstItem['data']['productType'],
                    'is_mirrored' => $isMirrored,
                    'scale' => $scale,
                    'total' => $group->count(),
                    'data' => $firstItem['data'],
                    'children' => $firstItem['children'],
                    'goods_indices' => $indices,
                    'is_customized' => $firstItem['customized']['is_customized'],
                    'customized_remark' => isset($firstItem['customized']['is_customized']) && $firstItem['customized']['is_customized']
                        ? ($firstItem['customized']['customized_remark'] ?? '')
                        : null,
                ];
            })
            ->values()
            ->toArray();

        return $groupedStats;
    }

    /**
     * 处理单个商品
     */
    protected function processSingleGoods(int $projectId, int $floorId, int $spaceId, array $goodsInfo, int $sort): void
    {
        $params['goods_id'] = $goodsInfo['goods_id'] ?? null;
        $params['option_id'] = $goodsInfo['option_id'] ?? null;
        $params['total'] = $goodsInfo['total'] ?? 1;
        $params['space_id'] = $spaceId;
        $params['floor_id'] = $floorId;
        $params['project_id'] = $projectId;
        $params['sort'] = $sort;
        $params['type'] =  1;
        $params['is_customized'] = $goodsInfo['is_customized'] ? 1 : 0;
        $params['customized_remark'] = $goodsInfo['customized_remark'] ?? null;
        $params['is_mirrored'] = $goodsInfo['is_mirrored'] ? 1 : 0;
        $params['components'] = [];
        if (count($goodsInfo['children']) == 1) {
            $model_chilren_data = $goodsInfo['children'][0]['model_chilren_data'];
            foreach ($model_chilren_data as $key => $item) {
                if ($goodsInfo['data']['id'] == $item['id']) {
                    $params['components'] = $this->getColorData($item);
                }
            }
        } else {
            $params['components'] = $this->getColorData($goodsInfo['data']);
        }

        $this->spliceGoods($params);
    }

    /**
     * 获取部件颜色处理数据
     */
    private function getColorData($component_data)
    {
        $model_data = $component_data['model_data'];
        $components = [];

        foreach ($model_data as $key => $value) {
            if ($value['visible'] == 0 && $value['changeLock'] == 1) {
                $new_data['component_id'] = $value['id'];
                $new_data['color_id'] = $value['curColor']['id'];
                $components[] = $new_data;
            }
        }

        return $components;
    }

    /**
     * 获取部件颜色处理数据string
     */
    private function getColorDataString($component_data)
    {
        $model_data = $component_data['model_data'];
        $components = [];

        foreach ($model_data as $key => $value) {
            $component_name = $value['name'];
            $color_name = $value['curColor']['name'];

            $color_component_string = $color_name . '/' . $component_name;
            $components[] = $color_component_string;
        }

        return implode("\n", $components);
    }

    /**
     * 处理拼接商品
     */
    protected function processCombinedGoods(int $projectId, int $floorId, int $spaceId, array $goodsData, array $old_design_data, int $goods_count, int $sort): void
    {
        $current_model = $this->getCurrentModelData($goodsData);
        $components_string = $this->getColorDataString($current_model);

        $result = $this->assembleGoodsData($projectId, $floorId, $spaceId, $goodsData, $components_string, $goods_count, $sort);
    }

    /**
     * 组装商品数据并处理
     */
    private function assembleGoodsData($projectId, $floorId, $spaceId, $goodsData, $components_string, $goods_count, $sort)
    {
        $addcomData = [];

        $addcomData['project_id'] = $projectId;
        $addcomData['floor_id'] = $floorId;
        $addcomData['space_id'] = $spaceId;

        $addcomData['goods_id'] = $goodsData['data']['goods_id'];

        $optionIds = (new GoodsComparator)->findModelChildrenDataOptionIds($goodsData['children']);

        $addcomData['optionIds'] = $optionIds;
        $addcomData['is_customized'] = $goodsData['customized']['is_customized'] ? 1 : 0;
        $addcomData['customized_remark'] = $goodsData['customized']['is_customized'] ? $goodsData['customized']['customized_remark'] ?? null : null;
        $addcomData['jsonData'] = $goodsData['dwg_data'];
        $addcomData['base64thumb'] = $goodsData['imgBase64'];
        $addcomData['optionData'] = $optionIds;
        $addcomData['productType'] = $goodsData['productType'];
        $addcomData['goods_uuid'] = $goodsData['goods_uuid'];
        $addcomData['modelData'] = json_encode($goodsData);
        $addcomData['components'] = $components_string;
        $addcomData['total'] = $goods_count;

        $service = new AssemblyGoodsService;
        $result = $service->assembleDwg($addcomData, false);

        $addcomData['goods_id'] = $result['goods_id'];
        $addcomData['option_id'] = $result['option_id'];
        $addcomData['type'] = 2;
        $addcomData['sort'] = $sort;

        return $this->spliceGoods($addcomData);
    }

    /**
     * 获取current model 数据
     */
    private function getCurrentModelData($goodsData)
    {
        foreach ($goodsData['children'] as $key1 => $value1) {
            foreach ($value1['model_chilren_data'] as $key2 => $value2) {
                if ($value1['modelType'] == $value2['modelType']) {
                    return $value2;
                }
            }
        }
        return [];
    }

    /**
     * 拼接商品加入购物车数据
     */
    private function spliceGoods(array $data): void
    {
        $data['components'] = is_array($data['components']) ? json_encode($data['components']) : $data['components'];
        $data = array(
            'member_id' => \YunShop::app()->getMemberId(),
            'uniacid' => \YunShop::app()->uniacid,
            'project_id' => $data['project_id'],
            'space_id' => $data['space_id'],
            'floor_id' => $data['floor_id'],
            'type' => $data['type'],
            'goods_id' => $data['goods_id'],
            'is_customized' => $data['is_customized'] ?: 0,
            'customized_remark' => $data['customized_remark'] ?: null,
            'sort' => $data['sort'],
            'total' => $data['total'],
            'option_id' => $data['option_id'] ?: 0,
            'components' => $data['components'],
            'components_hash' => md5($data['components']),
            'jsonData' => $data['jsonData'] ?: "",
            'modelData' => $data['modelData'] ?: "",
            'is_mirrored' => $data['is_mirrored'] ?: 0,
        );

        $cartModel = app('OrderManager')->make('MemberCart', $data);

        if (!$cartModel->save()) {
            throw new AppException("写入出错，加入空间失败！！！");
        }
    }



    /**
     * 提取modelType数组
     */
    private function extractModelTypes(array $goodsData): array
    {
        $modelTypes = [];

        if (isset($goodsData['children']) && is_array($goodsData['children'])) {
            foreach ($goodsData['children'] as $child) {
                if (isset($child['modelType'])) {
                    $modelTypes[] = $child['modelType'];
                }
            }
        }

        return $modelTypes;
    }
}
