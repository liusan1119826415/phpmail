<?php


namespace app\frontend\modules\project\services;

use app\backend\modules\goods\models\Category;
use app\backend\modules\goods\services\GoodsMeiliSearchService;
use app\common\exceptions\AppException;
use app\common\exceptions\ShopException;
use app\common\models\Address;
use app\common\models\Goods;
use app\common\models\goods\GoodsRelation;
use app\common\models\goods\GoodsStyle;
use app\common\models\goods\GoodsStyleRelations;
use app\frontend\models\GoodsOption;
use Meilisearch\Client;
use Illuminate\Support\Facades\DB;
use Overtrue\Pinyin\Pinyin;
use Yunshop\Supplier\common\models\Supplier;
use app\common\traits\ProcessGoodsOptionTrait;

class GoodsSearchService
{
    use ProcessGoodsOptionTrait;
    protected $client;

    protected $index;

    public function __construct()
    {

        $this->client = new Client(config('scout.meilisearch.host'), config('scout.meilisearch.key'));

        $this->index = $this->client->index('goods_option');
        $this->configureIndex();
    }




    /**
     * 将字符串序列化/JSON字段安全反序列化为数组
     */
    protected function deserializeToArray($value)
    {
        if (empty($value)) {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $un = @unserialize($value);
            if ($un !== false || $value === 'b:0;') {
                return is_array($un) ? $un : [];
            }
            $json = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return is_array($json) ? $json : [];
            }
        }
        return [];
    }

    /**
     * 标准化规格模型（hasManyOptionModels）数据
     */
    protected function normalizeOptionModelArray(array $model): array
    {
        return [
            'id' => isset($model['id']) ? (int)$model['id'] : 0,
            'option_id' => isset($model['option_id']) ? (int)$model['option_id'] : 0,
            'name' => $model['name'] ?? '',
            'originName' => $model['originName'] ?? '',
            'changeLock' => isset($model['changeLock']) ? (int)$model['changeLock'] : 0,
            'default_color' => $this->deserializeToArray($model['default_color'] ?? []),
            'select_color' => $this->deserializeToArray($model['select_color'] ?? []),
            'map_param' => $this->deserializeToArray($model['map_param'] ?? []),
            'meshs_name' => $model['meshs_name'] ?? '',
            'sort' => isset($model['sort']) ? (int)$model['sort'] : 0,
            'visible' => isset($model['visible']) ? (int)$model['visible'] : 0,
        ];
    }

    /**
     * 标准化商品下的 hasManyOptions 与嵌套的 hasManyOptionModels
     */
    protected function normalizeGoods($goods): array
    {
        if (!$goods) {
            return [];
        }
        $goodsArr = $goods->toArray();
        unset($goodsArr['has_many_goods_category']);
        unset($goodsArr['has_many_style_relations']);
        unset($goodsArr['has_many_goods_discount']);
        // 兼容关系键名（驼峰/下划线）
        $optionsKey = isset($goodsArr['has_many_options']) ? 'has_many_options' : (isset($goodsArr['hasManyOptions']) ? 'hasManyOptions' : null);
        if ($optionsKey && is_array($goodsArr[$optionsKey])) {
            foreach ($goodsArr[$optionsKey] as $idx => $option) {
                // 处理嵌套的 hasManyOptionModels
                $modelsKey = isset($option['has_many_option_models']) ? 'has_many_option_models' : (isset($option['hasManyOptionModels']) ? 'hasManyOptionModels' : null);
                $normalizedModels = [];
                if ($modelsKey && !empty($option[$modelsKey]) && is_array($option[$modelsKey])) {
                    foreach ($option[$modelsKey] as $m) {
                        $normalizedModels[] = $this->normalizeOptionModelArray($m);
                    }
                }

                // 规格基本字段规范化与媒体字段
                $option['id'] = isset($option['id']) ? (int)$option['id'] : 0;
                $option['goods_id'] = isset($option['goods_id']) ? (int)$option['goods_id'] : 0;
                if (isset($option['product_price'])) $option['product_price'] = (float)$option['product_price'];
                if (isset($option['show_sales'])) $option['show_sales'] = (int)$option['show_sales'];
                if (isset($option['comment_num'])) $option['comment_num'] = (int)$option['comment_num'];
                if (isset($option['stock'])) $option['stock'] = (int)$option['stock'];
                if (isset($option['length'])) $option['length'] = (float)$option['length'];
                if (isset($option['width'])) $option['width'] = (float)$option['width'];
                if (isset($option['height'])) $option['height'] = (float)$option['height'];
                if (isset($option['thumb'])) $option['thumb'] = yz_tomedia($option['thumb']);
                if (isset($option['cad_plan_model'])) $option['cad_plan_model'] = yz_tomedia($option['cad_plan_model']);
                if (isset($option['d3MaxUrl'])) $option['d3MaxUrl'] = yz_tomedia($option['d3MaxUrl']);
                if (isset($option['d3ModelUrl_weld'])) $option['d3ModelUrl_weld'] = yz_tomedia($option['d3ModelUrl_weld']);
                if (isset($option['d3ModelUrl_ori'])) $option['d3ModelUrl_ori'] = yz_tomedia($option['d3ModelUrl_ori']);
                if (isset($option['thumb3dModelUrl'])) $option['thumb3dModelUrl'] = yz_tomedia($option['thumb3dModelUrl']);

                // 放回标准化后的模型列表（统一为 option_models）
                $option['option_models'] = $normalizedModels;
                $goodsArr[$optionsKey][$idx] = $option;
            }
        }

        return $goodsArr;
    }


    public function reindexAll()
    {

        try {
            // 1. 获取数据库中的所有有效商品ID
            $validOptionIds = GoodsOption::where("is_default", 1)
                ->whereHas('goods', function ($query) {
                    $query->where('type2', 1)
                        ->where('status', 1)
                        ->whereNull('deleted_at');
                })
                ->pluck('id')
                ->toArray();



            \Log::debug('validOptionIds', $validOptionIds);
            //  print_r($validOptionIds);die; 

            // 2. 获取搜索引擎中的所有现有ID（使用正确的方式）
            $existingIds = $this->getAllExistingDocumentIds();




            // 3. 找出需要删除的ID（在搜索引擎但不在数据库）
            $idsToDelete = array_diff($existingIds, $validOptionIds);

            \Log::debug('idsToDelete', $idsToDelete);

            // print_r($idsToDelete);die;

            // 4. 删除无效文档
            if (!empty($idsToDelete)) {
                $deleteTask = $this->deleteDocumentsByIds($idsToDelete);

                //echo "删除无效文档: " . count($idsToDelete) . " 条\n";
                \Log::debug("删除无效文档: " . count($idsToDelete) . " 条");
            }

            // 5. 构建并添加/更新有效文档
            // $documents = [];
            // foreach ($validOptionIds as $id) {
            //     $doc = $this->buildDocument($id);
            //     if ($doc) {
            //         $documents[] = $doc;
            //     }
            // }

            // 2. 批量构建文档
            $documents = $this->buildBatchDocuments($validOptionIds);

            if (!empty($documents)) {
                $promises = $this->index->addDocumentsInBatches($documents, 1000, 'id');

                // 等待所有批次的任务完成
                $allSucceeded = true;
                $failedTasks = [];

                foreach ($promises as $task) {
                    // 等待每个promise完成并获取任务UID
                    $completedTask = $this->client->waitForTask($task['taskUid']);

                    if ($completedTask['status'] !== 'succeeded') {
                        $allSucceeded = false;
                        $failedTasks[] = [
                            'taskUid' => $task['taskUid'],
                            'error' => $completedTask['error']['message'] ?? '未知错误'
                        ];
                    }
                }

                if ($allSucceeded) {
                    \Log::debug("文档同步成功！新增/更新: " . count($documents) . " 条");
                    return true;
                } else {
                    throw new \Exception("文档添加失败，失败的任务: " . json_encode($failedTasks));
                }
            }



            \Log::debug("同步完成\n");
            return true;
        } catch (\Exception $e) {

            \Log::debug("同步失败: " . $e->getMessage());
            return false;
        }
    }





    /**
     * 删除多个文档（使用 filter 语法）
     */
    private function deleteDocumentsByIds(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        echo "准备删除 " . count($ids) . " 个文档\n";

        // 方法1：使用 OR 条件删除多个文档
        if (count($ids) <= 100) {
            // 构建 OR 条件：id = 1 OR id = 2 OR id = 3 ...
            $conditions = [];
            foreach ($ids as $id) {
                $conditions[] = "id = {$id}";
            }

            $filter = implode(' OR ', $conditions);

            try {
                echo "使用 filter 批量删除: {$filter}\n";
                $deleteTask = $this->index->deleteDocuments([
                    'filter' => $filter
                ]);

                $completedTask = $this->client->waitForTask($deleteTask['taskUid']);

                if ($completedTask['status'] === 'succeeded') {
                    echo "批量删除成功\n";
                    return;
                } else {
                    echo "批量删除失败: " . ($completedTask['error']['message'] ?? '未知错误') . "\n";
                }
            } catch (\Exception $e) {
                echo "批量删除异常: " . $e->getMessage() . "\n";
            }
        }

        // 方法2：如果文档很多，分批删除
        echo "文档较多，分批删除...\n";
        $batchSize = 50; // 每批50个
        $batches = array_chunk($ids, $batchSize);

        foreach ($batches as $batchIndex => $batch) {
            echo "删除批次 " . ($batchIndex + 1) . "/" . count($batches) . " (" . count($batch) . " 条)\n";

            $conditions = [];
            foreach ($batch as $id) {
                $conditions[] = "id = {$id}";
            }

            $filter = implode(' OR ', $conditions);

            try {
                $deleteTask = $this->index->deleteDocuments([
                    'filter' => $filter
                ]);

                $completedTask = $this->client->waitForTask($deleteTask['taskUid']);

                if ($completedTask['status'] !== 'succeeded') {
                    echo "批次删除失败: " . ($completedTask['error']['message'] ?? '未知错误') . "\n";
                }
            } catch (\Exception $e) {
                echo "批次删除异常: " . $e->getMessage() . "\n";
                // 继续处理下一个批次
            }
        }

        echo "已删除文档: " . count($ids) . " 条\n";
    }

    /**
     * 获取搜索引擎中所有文档ID（使用 search 方法 - 更高效）
     */
    private function getAllExistingDocumentIds(): array
    {
        $ids = [];
        $limit = 10000;
        $offset = 0;
        $maxPages = 100;

        for ($page = 0; $page < $maxPages; $page++) {
            try {
                // 使用 search 方法获取所有文档
                $results = $this->index->search('', [
                    'limit' => $limit,
                    'offset' => $offset,
                    'attributesToRetrieve' => ['id'], // 只获取id字段
                    'showRankingScore' => false,
                    'showMatchesPosition' => false,
                ]);

                $hits = $results->getHits();

                if (empty($hits)) {
                    echo "第 {$page} 页搜索到 0 个文档，结束循环\n";
                    break;
                }

                echo "第 {$page} 页搜索到 " . count($hits) . " 个文档\n";

                foreach ($hits as $hit) {
                    if (isset($hit['id'])) {
                        $ids[] = (string)$hit['id'];
                    }
                }

                // 如果获取到的文档数小于限制，说明是最后一页
                if (count($hits) < $limit) {
                    echo "最后一页，文档数少于限制数\n";
                    break;
                }

                $offset += $limit;
            } catch (\Exception $e) {
                echo "搜索文档失败: " . $e->getMessage() . "\n";

                // 如果是索引不存在的错误，直接返回空数组
                if (strpos($e->getMessage(), 'index_not_found') !== false) {
                    echo "索引不存在，返回空数组\n";
                    break;
                }

                throw $e;
            }
        }

        echo "通过搜索总共获取到 " . count($ids) . " 个文档ID\n";
        return $ids;
    }


    /**
     * @return \Meilisearch\Endpoints\Indexes
     */
    public function deleteIndex($indexName)
    {
        $this->client->deleteIndex($indexName);
    }


    /**
     * 构建单条商品规格索引文档
     */


    public  function reindexByGoodsId($goodsId)
    {
        // 1. 删除该 goods_id 的旧文档
        $optionIds = GoodsOption::where('goods_id', $goodsId)->where('is_default', 1)->pluck('id')->toArray();
        if (!empty($optionIds)) {
            $task = $this->index->deleteDocuments($optionIds);
            $this->client->waitForTask($task['taskUid']);
        }

        // 2. 重新构建文档
        $newOptionIds = GoodsOption::where('goods_id', $goodsId)->where('is_default', 1)->pluck('id')->toArray();
        $docs = [];
        foreach ($newOptionIds as $id) {
            $doc = $this->buildDocument($id);
            if ($doc) {
                $docs[] = $doc;
            }
        }

        // 3. 批量插入
        if (!empty($docs)) {
            $task = $this->index->addDocuments($docs, 'id');
            $completedTask = $this->client->waitForTask($task['taskUid']);
            if ($completedTask['status'] === 'succeeded') {
                \Log::debug("goods_id={$goodsId} 索引更新成功！");
            } else {
                \Log::error("索引更新失败: " . $completedTask['error']['message']);
            }
        }
    }


    /**
     * 删除规格/商品
     */
    public function deleteOption(int $optionId)
    {
        $this->index->deleteDocument($optionId);
    }


    public function conditions(): array
    {
        ##获取品牌数据
        $brand = Supplier::select("id", "store_name", "logo")->where('role_id', 1)->where('status', 1)->where('enable', 1)->get()->toArray();
        if ($brand) {
            $pinyin = new Pinyin(); // 使用 Overtrue Pinyin 库
            foreach ($brand as $k => $item) {
                $brand[$k]['logo'] = yz_tomedia($item['logo']);

                // 获取 store_name 的第一个中文字
                $firstChar = mb_substr($item['store_name'], 0, 1);

                // 获取第一个字的拼音首字母（转大写）
                $brand[$k]['first_letter'] = strtoupper($pinyin->abbr($firstChar));
            }
        }

        $data['brand'] = $brand;
        ##获取产品分类数据
        $category = Category::getAllCategoryGroupArray();
        $data['category'] = $category;
        ##工艺材质数据
        $craft_materials = GoodsStyle::select("id", "name")->where("type", 2)->get()->toArray();
        $data['craft_materials'] = $craft_materials;
        ##商品风格数据
        $goods_style = GoodsStyle::select("id", "name")->where("type", 1)->get()->toArray();
        $data['goods_style'] = $goods_style;
        ##产地数据
        $cityIds = Supplier::where('status', 1)->pluck("province_id")->all();
        $data['city_list'] = Address::select("id", "areaname")->whereIn("id", $cityIds)->get()->toArray();
        return $data;
    }



    public function conditionsV2($filters = []): array
    {
        $data = [];

        try {
            // 使用 MeiliSearch 进行搜索，获取匹配的文档
            $searchResults = $this->searchForConditions($filters);



            // 如果没有搜索结果，返回空数组
            if (empty($searchResults)) {
                return [
                    'brandIds' => [],
                    'categoryIds' => [],
                    'craft_materials' => [],
                    'goods_style' => [],
                    'cityIds' => []
                ];
            }

            // 从搜索结果中提取数据
            $brandIds = [];
            $categoryIds = [];
            $materialIds = [];
            $styleIds = [];
            $cityIds = [];

            foreach ($searchResults as $result) {

                // 提取分类ID
                if (!empty($result['category_id']) && is_array($result['category_id'])) {
                    $categoryIds = array_merge($categoryIds, $result['category_id']);
                }

                // 提取材质ID
                if (!empty($result['material_id']) && is_array($result['material_id'])) {
                    $materialIds = array_merge($materialIds, $result['material_id']);
                }

                // 提取风格ID
                if (!empty($result['style_id']) && is_array($result['style_id'])) {
                    $styleIds = array_merge($styleIds, $result['style_id']);
                }

                // 提取城市ID
                if (!empty($result['supplier_city_id'])) {
                    $cityIds[] = $result['supplier_city_id'];
                }
            }

            // 去重

            $categoryIds = array_unique($categoryIds);
            $materialIds = array_unique($materialIds);
            $styleIds = array_unique($styleIds);


            $cityIds = array_unique($cityIds);


            $data['categoryIds'] = $categoryIds;

            // 获取材质名称
            $data['craft_materials'] = GoodsStyle::whereIn('id', $materialIds)
                ->where('type', 2)
                ->select("id", "name")
                ->get()
                ->toArray();

            // 获取风格名称
            $data['goods_style'] = GoodsStyle::whereIn('id', $styleIds)
                ->where('type', 1)
                ->select("id", "name")
                ->get()
                ->toArray();

            $data['product_status']  = [
                1 => '特价',
                2 => '精选',
                3 => '库存'

            ];

            $data['cityIds'] = $cityIds;
        } catch (\Exception $e) {

            return [
                'brandIds' => [],
                'categoryIds' => [],
                'craft_materials' => [],
                'goods_style' => [],
                'cityIds' => []
            ];
        }

        return $data;
    }

    /**
     * 专门用于条件查询的搜索方法
     */
    protected function searchForConditions(array $filters = [])
    {
        $query = $filters['title'] ?? null;

        $options = [
            'limit' => 3000, // 获取足够多的结果来统计条件
            'attributesToRetrieve' => [
                'supplier_id',
                'category_id',
                'material_id',
                'style_id',
                'supplier_city_id'
            ]
        ];

        $filterArr = $this->getFilterData($filters);

        if (!empty($filterArr)) {

            $options['filter'] = $filterArr;
        }



        try {
            $results = $this->index->search($query, $options)->getHits();

            return $results;
        } catch (\Exception $e) {
            throw new ShopException('MeiliSearch 条件查询失败');
        }
    }

    /**
     * 检查 MeiliSearch 是否有数据
     */
    protected function hasMeiliSearchData(): bool
    {
        try {
            $stats = $this->index->stats();
            return $stats['numberOfDocuments'] > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function markCategoryDisabled(array $categories, array $enabledIds, $level = 1): array
    {
        foreach ($categories as &$cat) {
            // 当前分类是否可选
            // $cat['disabled'] = $level ==1?false:!in_array($cat['id'], $enabledIds);
            $cat['disabled'] = !in_array($cat['id'], $enabledIds);

            // 如果有子分类，递归处理
            if (!empty($cat['childrens'])) {
                $cat['childrens'] = $this->markCategoryDisabled($cat['childrens'], $enabledIds, $level + 1);
            }
        }
        return $categories;
    }

    protected function isEmptyFilter($filters): bool
    {
        foreach ($filters as $key => $value) {
            if (is_array($value) && !empty($value)) {
                return false; // 数组不为空，说明有筛选条件
            }

            if (!is_array($value) && !is_null($value) && $value !== '') {
                return false; // 字符串或数字不为空
            }
        }
        return true; // 所有都为空，才返回 true
    }

    public function conditionsCombined($filters = []): array
    {

        // 全量数据
        $allData = $this->conditions();

        // 首先检查 MeiliSearch 是否有数据
        if (!$this->hasMeiliSearchData()) {
            // 如果 MeiliSearch 没有数据，所有选项都禁用
            foreach (['brand', 'category', 'craft_materials', 'goods_style', 'city_list'] as $field) {
                foreach ($allData[$field] as &$item) {
                    $item['disabled'] = true;
                }
            }
            return $allData;
        }

        // 如果没有 filters，检查是否有搜索结果
        if ($this->isEmptyFilter($filters)) {
            // 检查空搜索是否有结果
            $hasResults = !empty($this->searchForConditions([]));

            foreach (['brand', 'category', 'craft_materials', 'goods_style', 'city_list'] as $field) {
                foreach ($allData[$field] as &$item) {
                    $item['disabled'] = !$hasResults; // 如果没有结果就禁用
                }
            }
            return $allData;
        }

        // 有 filters 的时候获取受限项
        $filteredData = $this->conditionsV2($filters);


        // 分类
        $enabledCategoryIds = $filteredData['categoryIds'] ?? [];
        $allData['category'] = $this->markCategoryDisabled($allData['category'], $enabledCategoryIds);

        // 材质
        $enabledMaterialIds = collect($filteredData['craft_materials'] ?? [])->pluck('id')->all();
        foreach ($allData['craft_materials'] as &$mat) {
            $mat['disabled'] = !in_array($mat['id'], $enabledMaterialIds);
        }

        // 风格
        $enabledStyleIds = collect($filteredData['goods_style'] ?? [])->pluck('id')->all();
        foreach ($allData['goods_style'] as &$style) {
            $style['disabled'] = !in_array($style['id'], $enabledStyleIds);
        }


        // 产地
        $enabledCityIds = $filteredData['cityIds'] ?? [];
        foreach ($allData['city_list'] as &$city) {
            $city['disabled'] = !in_array($city['id'], $enabledCityIds);
        }

        return $allData;
    }


    /**
     * 搜索
     */
    public function search($search)
    {
        $query = $search['title'] ?? null;
        if ($query) {
            $member_id = \YunShop::app()->getMemberId();
            SearchHistoryService::addSearchHistory(1, $query, $member_id);
        }
        $filters = $search;
        $orderField = request()->input('order_field');
        $limit = 20;
        $page = request()->page ?: 1;

        $fieldMapping = [
            'all' => 'display_order',
            'sale' => 'show_sales',
            'comment_num' => 'comment_num',
            'price' => 'price'
        ];

        $sortField = $fieldMapping[$orderField['name']] ?? 'display_order';
        $sortOrder = $orderField['order_by'] == 1 ? 'asc' : 'desc';

        $options = [
            'limit' => $limit,
            'offset' => ($page - 1) * $limit,
            'sort' => ["{$sortField}:{$sortOrder}"],
            'matchingStrategy' => 'all',

        ];
        $filter_data = $this->getFilterData($filters);


        if (!empty($filter_data)) {
            $options['filter'] = $filter_data;
        }


        try {

            $searchResult = $this->index->search($query, $options);
            $hits = $searchResult->getHits();
            foreach ($hits as $key => $hit) {
                $hits[$key]['title'] = $hit['goods_title'] . explode("+", $hit['option_title'])[0];
                $hits[$key]['thumb'] = yz_tomedia($hit['thumb']);
                $hits[$key]['goods']['is_stock'] = $hit['is_stock'];
                $hits[$key]['goods']['is_hot'] = $hit['is_hot'];
                $hits[$key]['goods']['is_discount'] = $hit['is_discount'];
            }
            $total = $searchResult->getEstimatedTotalHits();

            // 获取筛选条件数据
            $conditions = $this->conditionsCombined($filters);

            // 格式化分页数据
            $lastPage = ceil($total / $limit);

            $formattedData = [
                'data' => $hits,
                'per_page' => $limit,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => ($page - 1) * $limit + 1,
                'conditions' => $conditions,
            ];
            return $formattedData;
        } catch (\Exception $e) {
            throw new AppException('Meilisearch 搜索失败');
        }
    }


    public function search_goods_category($search)
    {
        // 获取筛选条件数据
        $conditions = $this->conditionsCombined($search);
        return $conditions;
    }


    /**
     * 搜索
     */
    public function search_goods_model($search)
    {
        $query = $search['title'] ?? null;

        $filters = $search;
        $orderField = request()->input('order_field');
        $limit = 20;
        $page = request()->page ?: 1;

        $fieldMapping = [
            'all' => 'display_order',
            'sale' => 'show_sales',
            'comment_num' => 'comment_num',
            'price' => 'price'
        ];

        $sortField = $fieldMapping[$orderField['name']] ?? 'display_order';
        $sortOrder = $orderField['order_by'] == 1 ? 'asc' : 'desc';

        $options = [
            'limit' => $limit,
            'offset' => ($page - 1) * $limit,
            'sort' => ["{$sortField}:{$sortOrder}"],
            'matchingStrategy' => 'all',

        ];
        $filter_data = $this->getFilterData($filters, 1);

        if (!empty($filter_data)) {
            $options['filter'] = $filter_data;
        }


        try {

            $searchResult = $this->index->search($query, $options);
            $hits = $searchResult->getHits();
            foreach ($hits as $key => $hit) {
                $hits[$key]['title'] = $hit['goods_title'] . explode("+", $hit['option_title'])[0];
                $hits[$key]['thumb'] = yz_tomedia($hit['thumb']);
                $hits[$key]['goods']['is_stock'] = $hit['is_stock'];
                $hits[$key]['goods']['is_hot'] = $hit['is_hot'];
                $hits[$key]['goods']['is_discount'] = $hit['is_discount'];
            }
            $total = $searchResult->getEstimatedTotalHits();



            // 格式化分页数据
            $lastPage = ceil($total / $limit);

            $formattedData = [
                'data' => $hits,
                'per_page' => $limit,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => ($page - 1) * $limit + 1,

            ];
            return $formattedData;
        } catch (\Exception $e) {
            \Log::error('Meilisearch 搜索失败', $e->getMessage());
            throw new AppException('Meilisearch 搜索失败');
        }
    }


    private function getFilterData($filters, $no = 0)
    {
        // 构建过滤条件 - 首先添加默认的 status = 1 条件
        $filterArr = ["status = 1", "enable = 1"]; // 默认条件
        if ($no == 1) {
            $filterArr[] = "thumb3dModelUrl IS NOT NULL";
        }

        $filter_data = null;
        // 参数映射关系
        $paramMapping = [
            'brandIds' => 'brand',
            'categoryIds' => 'category_id',
            'materialIds' => 'material_id',
            'styleIds' => 'style_id',
            'cityIds' => 'supplier_city_id',
            'bid_enable' => 'bid_enable',
            'is_stock' => 'is_stock',
            'is_discount' => 'is_discount',
            'is_hot' => 'is_hot',
            'lead_time' => 'lead_time'
        ];



        foreach ($filters as $key => $val) {
            if ($val == null || $val == '') continue;

            // 处理品牌ID
            if ($key === 'brandIds') {
                if (empty($val)) continue;
                if (!is_array($val)) $val = [$val];
                $vals = implode(',', array_map(function ($id) {
                    return '"' . (int)$id . '"';
                }, $val));
                $filterArr[] = "supplier_id IN [{$vals}]";
                continue;
            }

            // 处理分类ID
            if ($key === 'categoryIds') {
                if (empty($val)) continue;
                if (!is_array($val)) $val = [$val];
                $vals = implode(',', array_map('intval', $val));
                $filterArr[] = "category_id IN [{$vals}]";
                continue;
            }

            // 处理材质ID
            if ($key === 'materialIds') {
                if (empty($val)) continue;
                if (!is_array($val)) $val = [$val];
                $vals = implode(',', array_map('intval', $val));
                $filterArr[] = "material_id IN [{$vals}]";
                continue;
            }

            // 处理风格ID
            if ($key === 'styleIds') {
                if (empty($val)) continue;
                if (!is_array($val)) $val = [$val];
                $vals = implode(',', array_map('intval', $val));
                $filterArr[] = "style_id IN [{$vals}]";
                continue;
            }


            // 处理城市ID（兼容 cityIds 与 supplier_city_id 两种入参）
            if ($key === 'cityIds' || $key === 'supplier_city_id') {
                if (empty($val)) continue;
                if (is_array($val)) {
                    $vals = implode(',', array_map('intval', $val));
                    $filterArr[] = "supplier_city_id IN [{$vals}]";
                } else {
                    $filterArr[] = "supplier_city_id = " . (int)$val;
                }

                continue;
            }

            // 处理价格范围
            if ($key === 'start_price' || $key === 'end_price') {
                // 价格范围处理会在后面统一处理
                continue;
            }

            // 处理尺寸范围
            if ($key === 'start_size' || $key === 'end_size') {
                // 尺寸范围处理会在后面统一处理
                continue;
            }

            // 处理其他直接映射的字段
            if (isset($paramMapping[$key])) {
                $fieldName = $paramMapping[$key];
                if (!is_array($val)) {
                    if ($key == "bid_enable") {
                        $arr = [
                            1 => 0,
                            2 => 1,
                        ];
                        $val = $arr[$val];
                    }
                    $filterArr[] = "{$fieldName} = " . (int)$val;
                } else {
                    $vals = implode(',', array_map('intval', $val));
                    $filterArr[] = "{$fieldName} IN [{$vals}]";
                }
                continue;
            }
        }

        // 统一处理价格范围
        if (isset($filters['start_price']) || isset($filters['end_price'])) {
            $priceFilter = '';
            if (isset($filters['start_price']) && isset($filters['end_price'])) {
                $start = (float)$filters['start_price'];
                $end = (float)$filters['end_price'];
                $priceFilter = "price >= {$start} AND price <= {$end}";
            } elseif (isset($filters['start_price'])) {
                $start = (float)$filters['start_price'];
                $priceFilter = "price >= {$start}";
            } elseif (isset($filters['end_price'])) {
                $end = (float)$filters['end_price'];
                $priceFilter = "price <= {$end}";
            }
            if ($priceFilter) {
                $filterArr[] = $priceFilter;
            }
        }

        // 统一处理尺寸范围
        if (isset($filters['start_size']) || isset($filters['end_size'])) {
            $sizeFilter = '';
            if (isset($filters['start_size']) && isset($filters['end_size'])) {
                $start = (float)$filters['start_size'];
                $end = (float)$filters['end_size'];
                $sizeFilter = "length >= {$start} AND length <= {$end}";
            } elseif (isset($filters['start_size'])) {
                $start = (float)$filters['start_size'];
                $sizeFilter = "length >= {$start}";
            } elseif (isset($filters['end_size'])) {
                $end = (float)$filters['end_size'];
                $sizeFilter = "length <= {$end}";
            }
            if ($sizeFilter) {
                $filterArr[] = $sizeFilter;
            }
        }

        // 添加过滤条件到选项
        if (!empty($filterArr)) {
            $filter_data = implode(' AND ', $filterArr);
        }
        return $filter_data;
    }


    /*public function updateData()
    {

//        $goods = Goods::with(['relatedGoods', 'relatedByGoods'])->findOrFail(490);
//
//        $allRelations = $goods->getAllRelatedGoods();




        $goods = Goods::where('type2', 1)->get();

        foreach ($goods as $good) {
            $related_goods_ids = $good->related_goods_id ? explode(",", $good->related_goods_id) : [];

            foreach ($related_goods_ids as $related_id) {
                if (empty($related_id)) {
                    continue;
                }

                $related_id = trim($related_id);

                // 检查关联商品是否存在
                $relatedGoods = Goods::find($related_id);
                if (!$relatedGoods) {
                    \Log::warning("关联商品ID {$related_id} 不存在，跳过");
                    continue;
                }

                // 检查是否已经存在关联（正向或反向）
                $existingRelation = GoodsRelation::where(function($query) use ($good, $related_id) {
                        $query->where('goods_id', $good->id)
                            ->where('related_goods_id', $related_id);
                    })
                    ->orWhere(function($query) use ($good, $related_id) {
                        $query->where('goods_id', $related_id)
                            ->where('related_goods_id', $good->id);
                    })
                    ->exists();

                if ($existingRelation) {
                    \Log::info("商品 {$good->id} 和 {$related_id} 已存在关联，跳过");
                    continue;
                }

                // 检查是否是自关联
                if ($good->id == $related_id) {
                    \Log::info("商品 {$good->id} 尝试自关联，跳过");
                    continue;
                }

                try {
                    // 插入关联关系
                    GoodsRelation::create([
                        'goods_id' => $good->id,
                        'related_goods_id' => $related_id,
                    ]);

                    \Log::info("成功创建关联：商品 {$good->id} -> 商品 {$related_id}");

                } catch (\Exception $e) {
                    \Log::error("创建关联失败：商品 {$good->id} -> 商品 {$related_id}，错误：" . $e->getMessage());
                }
            }
        }

        return "数据迁移完成";
    }*/


    public function getSearchData($search_type)
    {
        try {


            switch ($search_type) {
                case 'price_range':
                    $query = GoodsOption::whereHas('goods', function ($query) {
                        $query->where('status', 1)->where('type2', 1);
                    })->whereHas('beLongsToSupplier', function ($query) {
                        $query->where('status', 1)->where('enable', 1);
                    });
                    $result = [
                        'min' => intval($query->min('product_price')),
                        'max' => intval($query->max('product_price'))
                    ];
                    break;

                case 'size_range':
                    $query = GoodsOption::whereHas('goods', function ($query) {
                        $query->where('status', 1)->where('type2', 1);
                    })->whereHas('beLongsToSupplier', function ($query) {
                        $query->where('status', 1)->where('enable', 1);
                    });
                    $result = [
                        'min' => intval($query->min('length')),
                        'max' => intval($query->max('length'))
                    ];
                    break;

                case 'brand':
                    // 如果是品牌查询，可能需要返回品牌列表
                    $result = Supplier::select("id", "store_name", "logo")
                        ->where('role_id', 1)
                        ->where('status', 1)
                        ->where('enable', 1)
                        ->get();

                    if ($result->isNotEmpty()) {
                        $pinyin = new Pinyin(); // 使用 Overtrue Pinyin 库

                        $result = $result->map(function ($item) use ($pinyin) {
                            $item->logo = yz_tomedia($item->logo);

                            // 获取 store_name 的第一个中文字
                            $firstChar = mb_substr($item->store_name, 0, 1);

                            // 获取第一个字的拼音首字母（转大写）
                            $item->first_letter = strtoupper($pinyin->abbr($firstChar));

                            return $item;
                        })->sortBy('first_letter') // 按首字母正序排序
                            ->values() // 重置键名
                            ->toArray();
                    }
                    break;

                default:
                    throw new AppException("不支持的搜索类型");
            }

            return $result;
        } catch (\Exception $e) {
            throw new AppException('查询失败');
        }
    }
}
