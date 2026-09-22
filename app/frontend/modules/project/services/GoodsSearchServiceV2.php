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
use Illuminate\Support\Facades\Log;
use Meilisearch\Client;
use Illuminate\Support\Facades\DB;
use Overtrue\Pinyin\Pinyin;
use Yunshop\Supplier\common\models\Supplier;
use app\common\traits\ProcessGoodsOptionTrait;
use Yunshop\Supplier\common\models\GoodsCategorySort;

class GoodsSearchServiceV2
{
    use ProcessGoodsOptionTrait;
    protected $client;

    protected $index;

    protected $searchableAttributes = [];

    public function __construct()
    {

        $this->client = new Client(config('scout.meilisearch.host'), config('scout.meilisearch.key'));

        $this->index = $this->client->index('goods_option');
        $this->configureIndex();
    }




    /**
     * 核心搜索逻辑（统一入口）
     * @param array $search       搜索参数
     * @param bool  $isDesk       是否为桌面端（传递给轮询/排序方法）
     * @param bool  $recordHistory 是否记录搜索历史
     * @return mixed
     */
    private function coreSearch($search, $isDesk = false, $recordHistory = true)
    {
        $query = $search['title'] ?? '';

        // 1. 记录搜索历史
        if ($recordHistory && $query) {
            $member_id = \YunShop::app()->getMemberId();
            SearchHistoryService::addSearchHistory(1, $query, $member_id);
        }

        // 2. 根据 search_type 调整搜索属性和过滤条件
        if (!empty($search['search_type'])) {
            switch ($search['search_type']) {
                case 1: // 分类

                    if (trim($query) === '') {
                        // 空查询时不作额外处理，保持原搜索行为
                        break;
                    }
                    $this->searchableAttributes = ['category_name'];

                    $catIds = $this->getCategoryIdsByName($query);
                    if (empty($catIds)) {
                        return $this->emptyResult(20, $search);
                    }
                    $search['categoryIds'] = array_merge(
                        $search['categoryIds'] ?? [],
                        $catIds
                    );
                    $query = ''; // 清空 query，由过滤器接管
                    break;
                case 2: // 产品
                   // $this->searchableAttributes = ['goods_title', 'option_title'];
                    $this->searchableAttributes = ['title'];
                    break;
                case 3: // 品牌
                    $this->searchableAttributes = ['supplier_name'];
                    break;
                    // 可继续扩展其他类型
            }
        }

        $filters = $search;
        $orderField = request()->input('order_field');
        $limit = 20;
        $page = request()->page ?: 1;

        $fieldMapping = [
            'all'        => 'display_order',
            'sale'       => 'show_sales',
            'comment_num' => 'comment_num',
            'price'      => 'price'
        ];

        $sortField = $fieldMapping[$orderField['name']] ?? 'display_order';
        $sortOrder = $orderField['order_by'] == 1 ? 'asc' : 'desc';

        // 综合排序 -> 轮询排序；其他排序 -> 原有排序
        if ($orderField['name'] == 'all') {
            return $this->searchWithRoundRobin($query, $filters, $page, $limit, $isDesk);
        } else {
            return $this->searchWithOriginalSort($query, $filters, $page, $limit, $sortField, $sortOrder, $isDesk);
        }
    }

    // 原 search 方法（普通端，记录历史，非桌面）
    public function search($search)
    {
        return $this->coreSearch($search, false, true);
    }

    // 原 search_goods_model 方法（桌面端，不记录历史，但同样支持 search_type）
    public function search_goods_model($search)
    {
        return $this->coreSearch($search, true, false);
    }


    public function search_goods_category($search)
    {
        // 获取筛选条件数据
        $conditions = $this->conditionsCombined($search);
        return $conditions;
    }






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




    private function applySearchableAttributes(array &$options): void
    {
        if (!empty($this->searchableAttributes)) {
            $options['attributesToSearchOn'] = $this->searchableAttributes;
        }
    }

    private function getCategoryIdsByName(string $name): array
    {
        if (empty($name)) {
            return [];
        }

        // 1. 模糊匹配所有分类（可限制 level 层级范围，按需）
        $matchedIds = \app\backend\modules\goods\models\Category::where('name', 'like', "%{$name}%")
            ->where('enabled', 1)
            ->pluck('id')
            ->toArray();

        if (empty($matchedIds)) {
            return [];
        }

        // 2. 递归收集所有子分类ID
        $allIds = [];
        foreach ($matchedIds as $id) {
            $allIds[] = $id;
            $allIds = array_merge($allIds, $this->getAllChildCategoryIds($id));
        }

        return array_unique($allIds);
    }

    /**
     * 递归获取某个分类的所有子分类ID（包括孙级等）
     */
    private function getAllChildCategoryIds(int $parentId): array
    {
        $childIds = \app\backend\modules\goods\models\Category::where('parent_id', $parentId)
            ->where('enabled', 1)
            ->pluck('id')
            ->toArray();

        $descendants = [];
        foreach ($childIds as $childId) {
            $descendants[] = $childId;
            // 递归找下一级
            $descendants = array_merge($descendants, $this->getAllChildCategoryIds($childId));
        }
        return $descendants;
    }








    /**
     * 使用轮询排序进行搜索 - 基于主分类(best_category_id)的三阶段轮询
     */
    private function searchWithRoundRobin($query, $filters, $page, $limit, $is_desk = 0)
    {
        try {


            // 1. 获取优先分类配置（建议缓存）
            $categoryNumber = \Setting::get("plugin.supplier.category_number") ?: 0;
            $categoryNumber = is_array($categoryNumber) ? ($categoryNumber['number'] ?? 0) : 0;

            // 2. 获取所有分类排序数据（建议缓存）
            $allCategorySorts = GoodsCategorySort::query()
                ->orderBy('category_sort', 'asc')
                ->get();

            // 3. 分割优先分类和剩余分类
            $priorityCategories = $allCategorySorts->take($categoryNumber);
            $remainingCategories = $allCategorySorts->skip($categoryNumber);

            // 4. 构建基础过滤器
            $baseFilter = $this->getFilterData($filters, $is_desk);

            // 5. 获取各阶段总数（基于 best_category_id 精确匹配）
            $priorityTotal = $this->countGoodsInCategoriesByPrimary($query, $baseFilter, $priorityCategories);
            $remainingTotal = $this->countGoodsInCategoriesByPrimary($query, $baseFilter, $remainingCategories);
            $unsortedTotal = $this->countUnsortedGoodsByPrimary($query, $baseFilter, $allCategorySorts);

            $totalCount = $priorityTotal + $remainingTotal + $unsortedTotal;
            if ($totalCount == 0) {
                return $this->emptyResult($limit, $filters);
            }

            // 6. 计算当前页数据所在阶段及内部偏移
            $offset = ($page - 1) * $limit;
            $stageData = $this->locateStageForOffset($offset, $limit, $priorityTotal, $remainingTotal, $unsortedTotal);

            // 7. 按需从各阶段拉取商品
            $pageGoods = [];
            if ($stageData['priority']['need'] > 0) {
                $pageGoods = array_merge($pageGoods, $this->fetchFromPriorityStage(
                    $query,
                    $baseFilter,
                    $priorityCategories,
                    $stageData['priority']['offset'],
                    $stageData['priority']['need']
                ));
            }
            if ($stageData['remaining']['need'] > 0) {
                $pageGoods = array_merge($pageGoods, $this->fetchFromRemainingStage(
                    $query,
                    $baseFilter,
                    $remainingCategories,
                    $stageData['remaining']['offset'],
                    $stageData['remaining']['need']
                ));
            }
            if ($stageData['unsorted']['need'] > 0) {
                $pageGoods = array_merge($pageGoods, $this->fetchFromUnsortedStageByPrimary(
                    $query,
                    $baseFilter,
                    $allCategorySorts,
                    $stageData['unsorted']['offset'],
                    $stageData['unsorted']['need']
                ));
            }

            // 8. 格式化结果
            $formattedResults = $this->formatResults($pageGoods);

            // 9. 返回数据
            return [
                'data' => $formattedResults,
                'per_page' => $limit,
                'total' => $totalCount,
                'last_page' => ceil($totalCount / $limit),
                'from' => $offset + 1,
                'conditions' => $this->conditionsCombined($filters),
            ];
        } catch (\Exception $e) {

          //  print_r($e->getMessage());
     
            \Log::error('轮询排序搜索失败: ' . $e->getMessage());
            throw new AppException('搜索失败');
        }
    }

    /**
     * 统计给定分类列表中每个分类作为主分类的商品总数
     */
    private function countGoodsInCategoriesByPrimary($query, $baseFilter, $categories): int
    {
        if ($categories->isEmpty()) {
            return 0;
        }
        $total = 0;
        foreach ($categories as $cat) {
            $total += $this->countGoodsInCategoryByPrimary($query, $baseFilter, $cat->sub_category_id);
        }
        return $total;
    }

    /**
     * 统计未排序分类的商品总数（best_category_id 不在任何已排序分类中）
     */
    private function countUnsortedGoodsByPrimary($query, $baseFilter, $allCategorySorts): int
    {
        $sortedCatIds = $allCategorySorts->pluck('sub_category_id')->toArray();
        $filter = $baseFilter;
        if (!empty($sortedCatIds)) {
            $filter .= ($filter ? ' AND ' : '') . 'best_category_id NOT IN [' . implode(',', $sortedCatIds) . ']';
        } else {
            // 如果没有已排序分类，则所有商品都属于未排序
            // 此时无需额外条件
        }
        return $this->getCountByFilter($query, $filter);
    }

    /**
     * 获取单个主分类的商品总数
     */
    private function countGoodsInCategoryByPrimary($query, $baseFilter, $categoryId): int
    {
        $filter = $this->buildPrimaryCategoryFilter($baseFilter, $categoryId);
        return $this->getCountByFilter($query, $filter);
    }

    /**
     * 构建主分类过滤器
     */
    private function buildPrimaryCategoryFilter($baseFilter, $categoryId): string
    {
        $filter = $baseFilter;
        if ($filter) {
            $filter .= ' AND ';
        }
        $filter .= "best_category_id = {$categoryId}";
        return $filter;
    }





    /**
     * 通用计数方法（发送 limit=0 的搜索请求）
     */
    private function getCountByFilter($query, $filter): int
    {
        $options = [
            'filter' => $filter,
            'limit' => 0,
            'matchingStrategy' => 'all',   // 新增：只匹配包含所有词的文档
        ];
        if (!empty($this->searchableAttributes)) {
            $options['attributesToSearchOn'] = $this->searchableAttributes;
        }
        $result = $this->index->search($query, $options);
        return $result->getEstimatedTotalHits() ?? 0;
    }

    /**
     * 构建分类过滤器（用于包含指定分类ID）
     */
    private function buildCategoryFilter($baseFilter, array $catIds): string
    {
        $filter = $baseFilter;
        if (!empty($catIds)) {
            $filter .= ($filter ? ' AND ' : '') . 'category_id IN [' . implode(',', $catIds) . ']';
        }
        return $filter;
    }


    /**
     * 根据全局偏移量计算需要从各阶段取多少条数据
     */
    private function locateStageForOffset($offset, $limit, $priorityTotal, $remainingTotal, $unsortedTotal): array
    {
        $result = [
            'priority' => ['offset' => 0, 'need' => 0],
            'remaining' => ['offset' => 0, 'need' => 0],
            'unsorted' => ['offset' => 0, 'need' => 0],
        ];
        $need = $limit;

        // 优先阶段
        if ($offset < $priorityTotal) {
            $take = min($need, $priorityTotal - $offset);
            $result['priority'] = ['offset' => $offset, 'need' => $take];
            $need -= $take;
            $offset = 0;
        } else {
            $offset -= $priorityTotal;
        }

        // 剩余阶段
        if ($need > 0 && $offset < $remainingTotal) {
            $take = min($need, $remainingTotal - $offset);
            $result['remaining'] = ['offset' => $offset, 'need' => $take];
            $need -= $take;
            $offset = 0;
        } else {
            $offset -= $remainingTotal;
        }

        // 未排序阶段
        if ($need > 0 && $offset < $unsortedTotal) {
            $take = min($need, $unsortedTotal - $offset);
            $result['unsorted'] = ['offset' => $offset, 'need' => $take];
        }

        return $result;
    }



    /**
     * 从优先分类池拉取数据（按分类顺序轮询）
     */
    private function fetchFromPriorityStage($query, $baseFilter, $categories, $offset, $limit): array
    {
        return $this->fetchFromCategoryPoolByPrimary($query, $baseFilter, $categories, $offset, $limit);
    }

    /**
     * 从剩余分类池拉取数据
     */
    private function fetchFromRemainingStage($query, $baseFilter, $categories, $offset, $limit): array
    {
        return $this->fetchFromCategoryPoolByPrimary($query, $baseFilter, $categories, $offset, $limit);
    }

    /**
     * 核心方法：从分类池中按轮询顺序获取商品（基于主分类）
     */
    private function fetchFromCategoryPoolByPrimary($query, $baseFilter, $categories, $offset, $limit): array
    {
        if ($categories->isEmpty() || $limit <= 0) {
            return [];
        }

        // 1. 获取每个分类的商品总数
        $catTotals = [];
        foreach ($categories as $cat) {
            $catTotals[$cat->sub_category_id] = $this->countGoodsInCategoryByPrimary($query, $baseFilter, $cat->sub_category_id);
        }

        // 2. 模拟轮询定位
        $states = [];
        foreach ($categories as $cat) {
            $cid = $cat->sub_category_id;
            $states[] = [
                'category_id' => $cid,
                'line_num' => (int)($cat->line_num ?? 5),
                'total' => $catTotals[$cid],
                'taken' => 0,
            ];
        }

        $globalOffset = $offset;
        $remaining = $limit;
        $results = [];

        while ($remaining > 0 && $this->hasMoreGoods($states)) {
            foreach ($states as &$state) {
                if ($remaining <= 0) break;
                $available = $state['total'] - $state['taken'];
                if ($available <= 0) continue;
                $takeThisRound = min($state['line_num'], $available);

                if ($globalOffset >= $takeThisRound) {
                    $globalOffset -= $takeThisRound;
                    $state['taken'] += $takeThisRound;
                    continue;
                }

                $startInBlock = $globalOffset;
                $take = min($takeThisRound - $startInBlock, $remaining);
                $goods = $this->fetchGoodsFromCategoryByPrimary(
                    $query,
                    $baseFilter,
                    $state['category_id'],
                    $state['taken'] + $startInBlock,
                    $take
                );
                $results = array_merge($results, $goods);
                $remaining -= count($goods);
                $state['taken'] += $startInBlock + count($goods);
                $globalOffset = 0;
            }
        }

        return $results;
    }

    /**
     * 从指定主分类中获取商品（支持扁平化排序）
     */
    private function fetchGoodsFromCategoryByPrimary($query, $baseFilter, $categoryId, $internalOffset, $limit): array
    {
        $filter = $this->buildPrimaryCategoryFilter($baseFilter, $categoryId);
        $sortField = "sort_cat_{$categoryId}"; // 扁平化排序字段

        $options = [
            'filter' => $filter,
            'sort' => ["{$sortField}:asc"],
            'offset' => $internalOffset,
            'limit' => $limit,
            'matchingStrategy' => 'all',   // 新增
        ];
        //  新增这一句
        $this->applySearchableAttributes($options);

        $result = $this->index->search($query, $options);
        return $result->getHits();
    }

    /**
     * 从未排序阶段拉取数据（best_category_id 不在已排序分类中）
     */
    private function fetchFromUnsortedStageByPrimary($query, $baseFilter, $allCategorySorts, $offset, $limit): array
    {
        $sortedCatIds = $allCategorySorts->pluck('sub_category_id')->toArray();
        $filter = $baseFilter;
        if (!empty($sortedCatIds)) {
            $filter .= ($filter ? ' AND ' : '') . 'best_category_id NOT IN [' . implode(',', $sortedCatIds) . ']';
        }

        $options = [
            'filter' => $filter,
            'sort' => ['best_category_last_sort:asc', 'best_category_two_last_sort:asc', 'best_category_final_sort:asc','id:asc'],
            'offset' => $offset,
            'limit' => $limit,
            'matchingStrategy' => 'all'   // 新增
        ];
        $this->applySearchableAttributes($options);
        $result = $this->index->search($query, $options);
        return $result->getHits();
    }

    /**
     * 判断分类池中是否还有剩余商品
     */
    private function hasMoreGoods($states): bool
    {
        foreach ($states as $state) {
            if ($state['taken'] < $state['total']) return true;
        }
        return false;
    }
















    /**
     * 返回空结果（复用已有逻辑）
     */
    private function emptyResult($limit, $filters)
    {
        return [
            'data' => [],
            'per_page' => $limit,
            'total' => 0,
            'last_page' => 0,
            'from' => 0,
            'conditions' => $this->conditionsCombined($filters),
        ];
    }



    /**
     * 使用原有排序方式进行搜索（价格、销量等排序）
     */
    private function searchWithOriginalSort($query, $filters, $page, $limit, $sortField, $sortOrder, $is_desk = false)
    {
        try {
            $options = [
                'limit' => $limit,
                'offset' => ($page - 1) * $limit,
                'sort' => ["{$sortField}:{$sortOrder}"],
                'matchingStrategy' => 'all',
            ];

            $filter_data = $this->getFilterData($filters, $is_desk);
            if (!empty($filter_data)) {
                $options['filter'] = $filter_data;
            }

            $searchResult = $this->index->search($query, $options);
            $hits = $searchResult->getHits();
            $formattedResults = $this->formatResults($hits);
            $total = $searchResult->getEstimatedTotalHits();

            // 获取筛选条件数据
            $conditions = $this->conditionsCombined($filters);

            // 格式化分页数据
            $lastPage = ceil($total / $limit);
            $result = [
                'data' => $formattedResults,
                'per_page' => $limit,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => ($page - 1) * $limit + 1
            ];

            if ($is_desk != 1) {
                $result['conditions'] = $conditions;
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Meilisearch 搜索失败: ' . $e->getMessage());
            throw new AppException('搜索失败');
        }
    }

    /**
     * 格式化结果
     */
    private function formatResults($hits)
    {
        foreach ($hits as $key => $hit) {
            $hits[$key]['title'] = $hit['goods_title'] . explode("+", $hit['option_title'])[0];
            $hits[$key]['thumb'] = yz_tomedia($hit['thumb']);
            $hits[$key]['option_first_title'] = explode("+", $hit['option_title'])[0];
            $hits[$key]['goods']['is_stock'] = $hit['is_stock'];
            $hits[$key]['goods']['is_hot'] = $hit['is_hot'];
            $hits[$key]['goods']['is_discount'] = $hit['is_discount'];
        }

        return $hits;
    }

















    private function getFilterData($filters, $no = false)
    {
        // 构建过滤条件 - 首先添加默认的 status = 1 条件
        $filterArr = ["status = 1", "enable = 1"]; // 默认条件
        if ($no) {
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



    private function conditionsCombined($filters = []): array
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
            throw new ShopException('Meilisearch 条件查询失败');
        }
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
            $cat['disabled'] = $level == 1 ? false : !in_array($cat['id'], $enabledIds);
            //  $cat['disabled'] = !in_array($cat['id'], $enabledIds);

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
}
