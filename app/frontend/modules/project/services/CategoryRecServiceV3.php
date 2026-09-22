<?php

namespace app\frontend\modules\project\services;

use Yunshop\Supplier\common\models\CategoryRecommend;
use app\common\models\goods\GoodsSpecCategory;
use app\common\models\GoodsOption;
use Illuminate\Support\Facades\Log;
use app\common\exceptions\AppException;
use Meilisearch\Client;
use app\common\traits\ProcessGoodsOptionTrait;
use Illuminate\Support\Facades\Cache;

class CategoryRecServiceV3
{
    use ProcessGoodsOptionTrait;
    
    public $goods_id;
    private $client;
    private $index;
    
    // 用于存储已推荐的商品ID，避免重复
    private $recommendedGoodsIds = [];
    
    // 缓存key前缀
    const CACHE_KEY_PREFIX = 'category_rec_goods_';
    // 缓存过期时间（秒）
    const CACHE_EXPIRE = 3600;
    
    public function __construct($goods_id)
    {
        $this->goods_id = $goods_id;
        $this->initMeilisearch();
    }

    /**
     * 初始化Meilisearch客户端
     */
    private function initMeilisearch()
    {
        $this->client = new Client(config('scout.meilisearch.host'), config('scout.meilisearch.key'));
        $this->index = $this->client->index('goods_option');
        $this->configureIndex();
    }



    /**
     * 获取推荐商品（统一格式）
     */
    public function getRecommendGoods($page = 1, $limit = 10, $refresh = false, $seed = null)
    {
        // 每次调用时重置已推荐商品ID
        $this->recommendedGoodsIds = [];
        
        $result = $this->getSpecCategory($page, $limit, true, $seed);
        return $this->formatGoodsList($result['list']);
    }

    /**
     * 获取商品的一级规格分类和推荐商品
     */
    public function getSpecCategory($page = 1, $limit = 10, $refresh = false, $seed = null)
    {
        // 1. 获取商品的一级规格分类
        $specCategories = GoodsSpecCategory::where('goods_id', $this->goods_id)
            ->where('category_type', 2)
            ->get();

        if ($specCategories->isEmpty()) {
            return [
                'list' => [],
                'total' => 0,
                'current_page' => $page,
                'per_page' => $limit
            ];
        }

        // 获取商品已有的分类ID（用于过滤）
        $existingCategoryIds = $specCategories->pluck('category_id')->toArray();

        $recommendResults = [];
        $totalCount = 0;

        // 如果是刷新请求，使用传入的种子或生成新的随机种子
        if ($refresh) {
            $randomSeed = $seed ?: $this->generateRandomSeed();
        } else {
            $randomSeed = null;
        }

        // 用于记录已经处理过的推荐分类组合
        $processedCombinations = [];

        foreach ($specCategories as $specCategory) {
            // 2. 根据规格分类ID查找对应的推荐分类
            $categoryRecommends = CategoryRecommend::where('category_id', $specCategory->category_id)
                ->with(['ranges'])
                ->orderBy('sort', 'asc')
                ->get();

            if ($categoryRecommends->isEmpty()) {
                continue;
            }

            // 3. 获取商品的所有默认规格价格
            $defaultOptions = GoodsOption::where('goods_id', $this->goods_id)
                ->where('spec_item_id', $specCategory->spec_item_id)
                ->where('is_default', 1)
                ->get();
            
            if ($defaultOptions->isEmpty()) {
                continue;
            }

            // 4. 遍历每个默认规格
            foreach ($defaultOptions as $defaultOption) {
                $productPrice = floatval($defaultOption->product_price);
                
                // 遍历推荐分类
                foreach ($categoryRecommends as $recommend) {
                    // 过滤掉商品一级规格已经有的商品分类
                    if (in_array($recommend->recommend_category_id, $existingCategoryIds)) {
                        continue;
                    }

                    foreach ($recommend->ranges as $range) {
                        $priceMin = floatval($range->price_min);
                        $priceMax = floatval($range->price_max);
                        
                        // 检查商品价格是否在区间内
                        if ($productPrice >= $priceMin && $productPrice <= $priceMax) {
                            // 创建唯一标识，避免重复处理同一个推荐分类
                            $combinationKey = $recommend->recommend_category_id . '_' . $range->id;
                            
                            if (in_array($combinationKey, $processedCombinations)) {
                                continue;
                            }
                            
                            $processedCombinations[] = $combinationKey;
                            
                            // 5. 根据比例计算价格区间
                            $ratioMin = floatval($range->ratio_min);
                            $ratioMax = floatval($range->ratio_max);
                            
                            // 计算推荐分类的商品价格区间
                            $recPriceMin = $productPrice * ($ratioMin / 100);
                            $recPriceMax = $productPrice * ($ratioMax / 100);

                 
                            // 6. 使用Meilisearch查询推荐分类下的商品
                            $recommendGoodsData = $this->searchRecommendGoods(
                                $recommend->recommend_category_id,
                                $recPriceMin,
                                $recPriceMax,
                                $page,
                                $limit,
                                $refresh,
                                $randomSeed,
                                $this->recommendedGoodsIds
                            );

                            

                    
                            
                            // 过滤掉已经推荐过的商品
                            $filteredGoods = $this->filterDuplicateGoods($recommendGoodsData['data']);
                            
                            if (!empty($filteredGoods)) {
                                $recommendResults[] = [
                                    'recommend_category' => [
                                        'id' => $recommend->recommend_category_id,
                                        'sort' => $recommend->sort,
                                    ],
                                    'recommend_goods' => $filteredGoods,
                                    'goods_pagination' => [
                                        'total' => count($filteredGoods),
                                        'current_page' => $page,
                                        'per_page' => $limit,
                                        'last_page' => 1
                                    ]
                                ];
                                
                                $totalCount += count($filteredGoods);
                            }
                            
                            break;
                        }
                    }
                }
            }
        }

        // 按推荐分类的sort字段排序
        usort($recommendResults, function($a, $b) {
            return $a['recommend_category']['sort'] <=> $b['recommend_category']['sort'];
        });

        return [
            'list' => $recommendResults,
            'total' => $totalCount,
            'current_page' => $page,
            'per_page' => $limit,
            'is_random' => $refresh,
            'refresh_time' => $refresh ? time() : null,
            'seed' => $randomSeed // 返回种子，用于后续刷新
        ];
    }

    /**
     * 过滤重复的商品
     */
    private function filterDuplicateGoods($goodsList)
    {
        $filtered = [];
        
        foreach ($goodsList as $goods) {
            $goodsId = $goods['goods_id'];
            
            if (!in_array($goodsId, $this->recommendedGoodsIds)) {
                $this->recommendedGoodsIds[] = $goodsId;
                $filtered[] = $goods;
            }
        }
        
        return $filtered;
    }

    /**
     * 使用Meilisearch搜索推荐商品
     */
    private function searchRecommendGoods($recommendCategoryId, $minPrice, $maxPrice, $page = 1, $limit = 10, $random = false, $seed = null, $excludeIds = [])
    {
        try {
            // 构建筛选条件
            $filters = [
                'category_id' => $recommendCategoryId,
                'price' => ['min' => $minPrice, 'max' => $maxPrice],
                'goods_id' => ['operator' => '!=', 'value' => $this->goods_id],
                'status' => 1,
                'enable' => 1, 
            ];

            // 如果有已推荐的商品ID，排除它们
            // if (!empty($excludeIds)) {
            //     $excludeIdsStr = implode(',', $excludeIds);
            //     $filters['goods_id'] = [
            //         'operator' => 'NOT IN',
            //         'value' => $excludeIdsStr
            //     ];
            // }

            if ($random) {
    
                // 使用种子生成随机排序
                $seedValue = $seed ? hexdec(substr($seed, 0, 8)) : mt_rand();
                
                // 获取多种排序策略组合
                $sortStrategies = $this->getMultipleRandomSortStrategies($seedValue, $page);
                
                
                // 尝试多种排序策略直到获取足够数量的商品
                $allGoods = [];
                $remainingLimit = $limit;
                
                foreach ($sortStrategies as $index => $sortConfig) {
                    if ($remainingLimit <= 0) break;
                    
                    // 根据不同的排序策略获取商品
                    $strategyResult = $this->searchWithRandomSort(
                        '', 
                        $filters, 
                        $page, 
                        $remainingLimit * 2, // 多取一些以应对重复
                        $sortConfig
                    );

      
                    
                    // 过滤已存在的商品
                    // foreach ($strategyResult['data'] as $goods) {
                    //     if (!in_array($goods['goods_id'], $this->recommendedGoodsIds) && 
                    //         !in_array($goods['goods_id'], array_column($allGoods, 'goods_id'))) {
                    //         $allGoods[] = $goods;
                    //         $remainingLimit--;
                    //         if ($remainingLimit <= 0) break;
                    //     }
                    // }
                }
                
                $searchResult = [
                    'data' => $strategyResult['data'],
                    'total' => count($strategyResult['data']),
                    'current_page' => $page,
                    'per_page' => $limit,
                    'last_page' => 1
                ];
            } else {
          
                $searchResult = $this->searchWithOriginalSort(
                    '', 
                    $filters, 
                    $page, 
                    $limit, 
                    'best_category_final_sort', 
                    'asc', 
                    0
                );
            }

        
            return $searchResult;

        } catch (\Exception $e) {
            Log::error('Meilisearch推荐商品搜索失败: ' . $e->getMessage());
            return [
                'data' => [],
                'total' => 0,
                'current_page' => $page,
                'per_page' => $limit,
                'last_page' => 1
            ];
        }
    }

    /**
     * 获取多种随机排序策略
     */
    private function getMultipleRandomSortStrategies($seed, $page)
    {
        $strategies = [];
        
        $sortFields = [
            'price',
            'id', 
            'created_at', 
            'sale_count',
            'best_category_final_sort',
            'updated_at'
        ];
        
        $sortOrders = ['asc', 'desc'];
        
        // 基于种子生成多个不同的排序组合
        for ($i = 0; $i < 5; $i++) {
            $combinedSeed = $seed + $page * 100 + $i * 1000;
            
            $mainFieldIndex = ($combinedSeed + $i) % count($sortFields);
            $mainOrderIndex = floor(($combinedSeed + $i) / count($sortFields)) % count($sortOrders);
            
            $remainingFields = array_values(array_diff_key($sortFields, [$mainFieldIndex => $sortFields[$mainFieldIndex]]));
            $secondaryFieldIndex = floor(($combinedSeed + $i) / (count($sortFields) * count($sortOrders))) % count($remainingFields);
            $secondaryOrderIndex = floor(($combinedSeed + $i) / (count($sortFields) * count($sortOrders) * count($remainingFields))) % count($sortOrders);
            
            $strategies[] = [
                'main' => [
                    'field' => $sortFields[$mainFieldIndex],
                    'order' => $sortOrders[$mainOrderIndex]
                ],
                'secondary' => [
                    'field' => $remainingFields[$secondaryFieldIndex] ?? 'id',
                    'order' => $sortOrders[$secondaryOrderIndex] ?? 'asc'
                ],
                'tertiary' => [
                    'field' => 'id',
                    'order' => 'asc'
                ]
            ];
        }
        
        return $strategies;
    }

    /**
     * 带随机排序的搜索
     */
    private function searchWithRandomSort($query, $filters, $page, $limit, $sortConfig)
    {
        try {
            $sortRules = [
                "{$sortConfig['main']['field']}:{$sortConfig['main']['order']}",
                "{$sortConfig['secondary']['field']}:{$sortConfig['secondary']['order']}",
                "{$sortConfig['tertiary']['field']}:{$sortConfig['tertiary']['order']}"
            ];

            $options = [
                'limit' => $limit,
                'offset' => ($page - 1) * $limit,
                'sort' => $sortRules,
                'matchingStrategy' => 'all',
            ];

            $filter_data = $this->getFilterData($filters, 0);
            if (!empty($filter_data)) {
                $options['filter'] = $filter_data;
            }

            $searchResult = $this->index->search($query, $options);
            $hits = $searchResult->getHits();
            
            $formattedResults = $this->formatResults($hits);
            $total = $searchResult->getEstimatedTotalHits();

            $lastPage = ceil($total / $limit);
            
            return [
                'data' => $formattedResults,
                'per_page' => $limit,
                'total' => $total,
                'last_page' => $lastPage,
                'current_page' => $page,
                'from' => ($page - 1) * $limit + 1
            ];

        } catch (\Exception $e) {
            Log::error('Meilisearch 随机搜索失败: ' . $e->getMessage());
            throw new AppException('搜索失败');
        }
    }

    /**
     * 普通排序搜索
     */
    private function searchWithOriginalSort($query, $filters, $page, $limit, $sortField, $sortOrder, $is_desk = 0)
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

            $lastPage = ceil($total / $limit);
            $result = [
                'data' => $formattedResults,
                'per_page' => $limit,
                'total' => $total,
                'last_page' => $lastPage,
                'current_page' => $page,
                'from' => ($page - 1) * $limit + 1
            ];

            return $result;
        } catch (\Exception $e) {
            Log::error('Meilisearch 搜索失败: ' . $e->getMessage());
            throw new AppException('搜索失败');
        }
    }

    /**
     * 构建筛选条件
     */
    private function getFilterData($filters, $is_desk = 0)
    {
        $filterConditions = [];
        
        foreach ($filters as $key => $value) {
            if (empty($value)) {
                continue;
            }

            if ($key == 'price' && isset($value['min']) && isset($value['max'])) {
                $filterConditions[] = "price >= {$value['min']} AND price <= {$value['max']}";
            } elseif ($key == 'goods_id') {
                if (isset($value['operator']) && isset($value['value'])) {
                    if ($value['operator'] == 'NOT IN') {
                        $filterConditions[] = "goods_id NOT IN [{$value['value']}]";
                    } else {
                        $filterConditions[] = "goods_id {$value['operator']} {$value['value']}";
                    }
                } else {
                    $filterConditions[] = "goods_id = " . (is_string($value) ? "'{$value}'" : $value);
                }
            } elseif (is_array($value)) {
                $values = implode(',', array_map(function($item) {
                    return is_string($item) ? "'{$item}'" : $item;
                }, $value));
                $filterConditions[] = "{$key} IN [{$values}]";
            } else {
                $filterConditions[] = "{$key} = " . (is_string($value) ? "'{$value}'" : $value);
            }
        }

        return implode(' AND ', $filterConditions);
    }

    /**
     * 格式化搜索结果
     */
    private function formatResults($hits)
    {
        foreach ($hits as $key => $hit) {
            $hits[$key]['title'] = $hit['goods_title'] . (isset($hit['option_title']) ? ' ' . explode("+", $hit['option_title'])[0] : '');
            $hits[$key]['thumb'] = yz_tomedia($hit['thumb']);
            $hits[$key]['goods_id'] = $hit['goods_id'] ?? 0;
            $hits[$key]['product_price'] = $hit['price'] ?? 0;
            $hits[$key]['is_stock'] = $hit['is_stock'] ?? 0;
            $hits[$key]['is_hot'] = $hit['is_hot'] ?? 0;
            $hits[$key]['is_discount'] = $hit['is_discount'] ?? 0;
            
        }

        return $hits;
    }

    /**
     * 格式化商品列表
     */
    private function formatGoodsList($recommendResults)
    {
        $formattedList = [];
        
        foreach ($recommendResults as $result) {
            foreach ($result['recommend_goods'] as $goods) {
                $formattedList[] = [
                    'id' => $goods['id'],
                    'goods_id' => $goods['goods_id'],
                    'title' => $goods['title'],
                    'price' => $goods['price'],
                    'min_price' => $goods['price'],
                    'max_price' => $goods['price'],
                    'thumb' => $goods['thumb'],
                    'is_discount' => $goods['is_discount'] ?? 0,
                    'is_hot' => $goods['is_hot'] ?? 0,
                    'is_stock' => $goods['is_stock'] ?? 0
                ];
            }
        }
        
        return $formattedList;
    }

    /**
     * 生成随机种子
     */
    private function generateRandomSeed()
    {
        return md5(uniqid(mt_rand(), true) . microtime() . $this->goods_id . time() . rand(1, 10000));
    }

    /**
     * 刷新推荐商品（前端点击刷新按钮时调用）
     * 
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array
     */
    public function refreshRecommendGoods($page = 1, $limit = 10)
    {
        // 重置已推荐商品ID
        $this->recommendedGoodsIds = [];
        
        // 生成新的随机种子（确保每次刷新都不同）
        $newSeed = $this->generateRandomSeed();
        
        // 调用getSpecCategory并传入新的随机种子
        $result = $this->getSpecCategory($page, $limit, true, $newSeed);
        
        // 格式化并返回商品列表
        $formattedList = $this->formatGoodsList($result['list']);
        
        // 返回商品列表和分页信息
        return $formattedList;
    }

    /**
     * 获取下一页推荐商品（分页加载）
     */
    public function getNextPageGoods($page = 1, $limit = 10, $seed = null)
    {
        // 不清空已推荐商品ID，保持当前会话的推荐记录
        $result = $this->getSpecCategory($page, $limit, true, $seed);
        return $this->formatGoodsList($result['list']);
    }
}