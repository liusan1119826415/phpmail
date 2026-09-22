<?php

namespace app\frontend\modules\project\services;

use Yunshop\Supplier\common\models\CategoryRecommend;
use app\common\models\goods\GoodsSpecCategory;
use app\common\models\GoodsOption;
use app\common\models\Goods;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis; // 改用 Redis
use app\common\exceptions\AppException;
use Meilisearch\Client;
use Meilisearch\Contracts\SearchQuery;
use app\common\traits\ProcessGoodsOptionTrait;

class CategoryRecService
{
    use ProcessGoodsOptionTrait;

    public $goods_id;
    private $client;
    private $index;
    private $currentBrandId;
    private $recommendedGoodsIds = [];
    private $currentGoodsOptionIds = [];

    const CACHE_PREFIX = 'category_rec_';
    const CACHE_TTL = 300;                // 秒
    const RANDOM_SEED_CACHE_PREFIX = 'category_rec_random_seed_';
    const RANDOM_SEED_EXPIRE = 3600;      // 秒
    const MAX_SEARCH_LIMIT = 200;
    const DEFAULT_LIMIT = 4;

    const CAROUSEL_STATE_PREFIX = 'carousel_state_';
    const CAROUSEL_STATE_TTL = 3600;      // 秒
    const ALL_CATEGORIES_CACHE_PREFIX = 'all_categories_rec_';
    const RECOMMENDED_OPTS_PREFIX = 'recommended_opts_';

    public function __construct($goods_id)
    {
        $this->goods_id = $goods_id;
        $this->initMeilisearch();
        $this->initBrandId();
        $this->initCurrentOptionIds();
    }

    // ========= 私有缓存方法（替代 Cache 门面） =========
    private function cacheGet($key, $default = null)
    {
        $value = Redis::get($key);
        if ($value === null) {
            return $default;
        }
        return json_decode($value, true) ?? $default;
    }

    private function cacheSet($key, $value, $ttl)
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
        if ($ttl > 0) {
            Redis::setex($key, $ttl, $encoded);
        } else {
            Redis::set($key, $encoded);
        }
    }

    private function cacheDel($key)
    {
        Redis::del($key);
    }
    // ================================================

    private function initBrandId()
    {
        $goods = Goods::select('supp_id')->find($this->goods_id);
        $this->currentBrandId = $goods->supp_id ?? 0;
    }

    private function initCurrentOptionIds()
    {
        $this->currentGoodsOptionIds = GoodsOption::where('goods_id', $this->goods_id)
            ->pluck('id')
            ->toArray();
    }

    private function initMeilisearch()
    {
        $this->client = new Client(config('scout.meilisearch.host'), config('scout.meilisearch.key'));
        $this->index = $this->client->index('goods_option');
    }

    private function resolveSessionId($carouselSessionId = null)
    {
        return $carouselSessionId ?: (session_id() ?: 'default_session');
    }

    private function getRecommendedOptionIds($categoryId, $sessionId)
    {
        $key = self::RECOMMENDED_OPTS_PREFIX . $this->goods_id . '_' . $categoryId . '_' . $sessionId;
        return $this->cacheGet($key, []);
    }

    private function addRecommendedOptionIds($categoryId, array $newIds, $sessionId)
    {
        $key = self::RECOMMENDED_OPTS_PREFIX . $this->goods_id . '_' . $categoryId . '_' . $sessionId;
        $existing = $this->getRecommendedOptionIds($categoryId, $sessionId);
        $merged = array_unique(array_merge($existing, $newIds));
        $this->cacheSet($key, $merged, self::CACHE_TTL);
    }

    private function getCarouselState($carouselSessionId = null)
    {
        $sessionKey = $this->resolveSessionId($carouselSessionId);
        $cacheKey = self::CAROUSEL_STATE_PREFIX . $this->goods_id . '_' . $sessionKey;
        $state = $this->cacheGet($cacheKey);

        if (!$state) {
            $availableCategories = $this->getAvailableCategories();
            if (empty($availableCategories)) {
                return null;
            }

            $state = [
                'current_index'    => 0,
                'total_categories' => count($availableCategories),
                'category_ids'     => array_column($availableCategories, 'id'),
                'last_update'      => time()
            ];
            $this->cacheSet($cacheKey, $state, self::CAROUSEL_STATE_TTL);
        }

        return $state;
    }

    private function moveToNextCategory($carouselSessionId = null)
    {
        $sessionKey = $this->resolveSessionId($carouselSessionId);
        $cacheKey = self::CAROUSEL_STATE_PREFIX . $this->goods_id . '_' . $sessionKey;
        $state = $this->getCarouselState($carouselSessionId);
        if (!$state) return false;

        $state['current_index'] = ($state['current_index'] + 1) % $state['total_categories'];
        $state['last_update'] = time();
        $this->cacheSet($cacheKey, $state, self::CAROUSEL_STATE_TTL);
        return $state;
    }

    private function resetCarouselState($carouselSessionId = null)
    {
        $sessionKey = $this->resolveSessionId($carouselSessionId);
        $cacheKey = self::CAROUSEL_STATE_PREFIX . $this->goods_id . '_' . $sessionKey;
        $this->cacheDel($cacheKey);
        return $this->getCarouselState($carouselSessionId);
    }

    private function getAvailableCategories()
    {
        $specCategories = GoodsSpecCategory::where('goods_id', $this->goods_id)
            ->where('category_type', 2)
            ->get();

        if ($specCategories->isEmpty()) {
            return [];
        }

        $categories = [];
        $addedCategoryIds = [];

        foreach ($specCategories as $specCategory) {
            $categoryId = $specCategory->category_id;

            if (!isset($addedCategoryIds[$categoryId])) {
                $categories[] = [
                    'id'              => $categoryId,
                    'name'            => $specCategory->category_name ?? '',
                    'sort'            => $specCategory->sort ?? 0,
                    'is_self_category' => true
                ];
                $addedCategoryIds[$categoryId] = true;
            }

            $categoryRecommends = CategoryRecommend::where('category_id', $categoryId)
                ->with(['ranges'])
                ->orderBy('sort', 'asc')
                ->get();

            foreach ($categoryRecommends as $recommend) {
                $recommendCategoryId = $recommend->recommend_category_id;
                if (!isset($addedCategoryIds[$recommendCategoryId])) {
                    $categories[] = [
                        'id'                => $recommendCategoryId,
                        'name'              => '',
                        'sort'              => $recommend->sort ?? 999,
                        'is_self_category'  => false,
                        'has_ranges'        => !$recommend->ranges->isEmpty(),
                        'source_category_id' => $categoryId,
                        'recommend_id'      => $recommend->id,
                    ];
                    $addedCategoryIds[$recommendCategoryId] = true;
                }
            }
        }

        usort($categories, function ($a, $b) {
            if ($a['sort'] != $b['sort']) {
                return $a['sort'] <=> $b['sort'];
            }
            if ($a['is_self_category'] && !$b['is_self_category']) {
                return -1;
            }
            if (!$a['is_self_category'] && $b['is_self_category']) {
                return 1;
            }
            return 0;
        });

        return $categories;
    }

    public function getRecommendGoods($action = 'init', $excludeGoodsIds = [], $carouselSessionId = null)
    {
        $carouselSessionId = $this->resolveSessionId($carouselSessionId);
        $availableCategories = $this->getAvailableCategories();

        if (empty($availableCategories)) {
            return $this->emptyResponse();
        }

        // 清空所有可用分类的已推荐缓存
        foreach ($availableCategories as $category) {
            $key = self::RECOMMENDED_OPTS_PREFIX . $this->goods_id . '_' . $category['id'] . '_' . $carouselSessionId;
            $this->cacheDel($key);
        }

        $state = $this->getCarouselState($carouselSessionId);

        switch ($action) {
            case 'next':
                $state = $this->moveToNextCategory($carouselSessionId);
                break;
            case 'refresh':
                $this->refreshAllCategories();
                $state = $this->getCarouselState($carouselSessionId);
                break;
            case 'init':
            default:
                if (!$state) {
                    $state = $this->resetCarouselState($carouselSessionId);
                }
                break;
        }

        if (!$state) {
            return $this->emptyResponse();
        }

        $currentCategoryId = $state['category_ids'][$state['current_index']];
        $result = $this->getSpecCategoryByCategory($currentCategoryId, $carouselSessionId);

        return [
            'current_category' => [
                'id'    => $result['category_id'],
                'name'  => $result['category_name'],
                'index' => $state['current_index'] + 1,
                'total' => $state['total_categories']
            ],
            'goods_list'          => $result['goods_list'],
            'available_categories' => $availableCategories,
            'carousel_info' => [
                'has_next'      => $state['total_categories'] > 1,
                'next_hint'     => '下一个推荐分类',
                'can_refresh'   => true,
                'refresh_hint'  => '刷新推荐',
                'refresh_time'  => $result['refresh_time'] ?? time()
            ],
            'state' => [
                'current_index' => $state['current_index'],
                'total'         => $state['total_categories']
            ]
        ];
    }

    public function getGoodsByCategory($categoryId, $carouselSessionId = null)
    {
        $availableCategories = $this->getAvailableCategories();
        $exists = in_array($categoryId, array_column($availableCategories, 'id'));
        if (!$exists) {
            return $this->emptyResponse();
        }

        $sessionId = $this->resolveSessionId($carouselSessionId);

        // 清空该分类的已推荐记录
        // $key = self::RECOMMENDED_OPTS_PREFIX . $this->goods_id . '_' . $categoryId . '_' . $sessionId;
        // $this->cacheDel($key);

        // 强制刷新随机种子，确保每次看到不同商品
        $this->refreshCategoryRandomSeed($categoryId);

        $result = $this->getSimpleCategoryGoods($categoryId, $sessionId);

        return [
            'current_category' => [
                'id'    => $result['category_id'],
                'name'  => $result['category_name'],
                'index' => null,
                'total' => count($availableCategories)
            ],
            'goods_list'          => $result['goods_list'],
            'available_categories' => $availableCategories
        ];
    }

    private function getSimpleCategoryGoods($targetCategoryId, $sessionId)
    {
        $randomSeed = $this->getCategoryRandomSeed($targetCategoryId);

        // 获取分类信息
        $availableCategories = $this->getAvailableCategories();
        $categoryInfo = null;
        foreach ($availableCategories as $cat) {
            if ($cat['id'] == $targetCategoryId) {
                $categoryInfo = $cat;
                break;
            }
        }

        if (!$categoryInfo) {
            $searchConfigs = ['requests' => [[
                'recommend_category_id' => $targetCategoryId,
                'filters' => [
                    'category_id' => $targetCategoryId,
                    'id'          => ['operator' => 'NOT IN', 'value' => $this->currentGoodsOptionIds],
                    'status'      => 1,
                    'enable'      => 1,
                ],
                'has_price_match' => false,
                'price_min'       => null,
                'price_max'       => null,
                'priority'        => 2,
                'random'          => !is_null($randomSeed),
                'seed'            => $randomSeed,
                'seed_index'      => 0,
            ]]];
        } elseif ($categoryInfo['is_self_category']) {
            $alreadyRecommended = $this->getRecommendedOptionIds($targetCategoryId, $sessionId);
            $baseExclude = array_merge($this->currentGoodsOptionIds, $alreadyRecommended);
            $searchConfigs = ['requests' => [[
                'recommend_category_id' => $targetCategoryId,
                'filters' => [
                    'category_id' => $targetCategoryId,
                    'id'          => ['operator' => 'NOT IN', 'value' => $baseExclude],
                    'status'      => 1,
                    'enable'      => 1,
                ],
                'has_price_match' => false,
                'price_min'       => null,
                'price_max'       => null,
                'priority'        => 2,
                'random'          => !is_null($randomSeed),
                'seed'            => $randomSeed,
                'seed_index'      => 0,
            ]]];
        } else {
            $searchConfigs = $this->buildRecommendCategorySearchConfig(
                $targetCategoryId,
                $categoryInfo['source_category_id'],
                $categoryInfo['recommend_id'],
                $randomSeed,
                $sessionId
            );
        }

        

        $allGoods = $this->parallelSearch($searchConfigs['requests'], $randomSeed);
        $processedGoods = $this->processCategoryGoodsWithBrandPriority($allGoods, self::DEFAULT_LIMIT);

        $alreadyRecommended = $this->getRecommendedOptionIds($targetCategoryId, $sessionId);
        if (empty($processedGoods) && !empty($alreadyRecommended)) {
            $key = self::RECOMMENDED_OPTS_PREFIX . $this->goods_id . '_' . $targetCategoryId . '_' . $sessionId;
            $this->cacheDel($key);

            // 重新构建（已推荐列表已空）
            if ($categoryInfo && !$categoryInfo['is_self_category']) {
                $searchConfigs = $this->buildRecommendCategorySearchConfig(
                    $targetCategoryId,
                    $categoryInfo['source_category_id'],
                    $categoryInfo['recommend_id'],
                    $randomSeed,
                    $sessionId
                );
            } else {
                $searchConfigs['requests'][0]['filters']['id']['value'] = $this->currentGoodsOptionIds;
            }
            $allGoods = $this->parallelSearch($searchConfigs['requests'], $randomSeed);
            $processedGoods = $this->processCategoryGoodsWithBrandPriority($allGoods, self::DEFAULT_LIMIT);
        }

        if (!empty($processedGoods)) {
            $this->addRecommendedOptionIds($targetCategoryId, array_column($processedGoods, 'id'), $sessionId);
        }

        return [
            'category_id'  => $targetCategoryId,
            'goods_list'   => $processedGoods,
            'random_seed'  => $randomSeed,
            'refresh_time' => time(),
        ];
    }

    private function getSpecCategoryByCategory($targetCategoryId, $sessionId)
    {
        $randomSeed = $this->getCategoryRandomSeed($targetCategoryId);

        $availableCategories = $this->getAvailableCategories();
        $categoryInfo = null;
        foreach ($availableCategories as $cat) {
            if ($cat['id'] == $targetCategoryId) {
                $categoryInfo = $cat;
                break;
            }
        }

        if (!$categoryInfo) {
            return $this->emptyCategoryResponse($targetCategoryId);
        }

        if ($categoryInfo['is_self_category']) {
            $specCategory = GoodsSpecCategory::where('category_id', $targetCategoryId)
                ->where('category_type', 2)
                ->first();
            if (!$specCategory) {
                return $this->emptyCategoryResponse($targetCategoryId);
            }
            $searchConfigs = $this->buildSelfCategorySearchConfig($specCategory, $randomSeed, $sessionId);
        } else {
            $searchConfigs = $this->buildRecommendCategorySearchConfig(
                $targetCategoryId,
                $categoryInfo['source_category_id'],
                $categoryInfo['recommend_id'],
                $randomSeed,
                $sessionId
            );
        }

        if (empty($searchConfigs['requests'])) {
            return $this->emptyCategoryResponse($targetCategoryId);
        }

        $allGoods = $this->parallelSearch($searchConfigs['requests'], $randomSeed);
        $processedGoods = $this->processCategoryGoodsWithBrandPriority($allGoods, self::DEFAULT_LIMIT);

        $alreadyRecommended = $this->getRecommendedOptionIds($targetCategoryId, $sessionId);
        if (empty($processedGoods) && !empty($alreadyRecommended)) {
            $key = self::RECOMMENDED_OPTS_PREFIX . $this->goods_id . '_' . $targetCategoryId . '_' . $sessionId;
            $this->cacheDel($key);

            if ($categoryInfo['is_self_category']) {
                $searchConfigs = $this->buildSelfCategorySearchConfig($specCategory, $randomSeed, $sessionId);
            } else {
                $searchConfigs = $this->buildRecommendCategorySearchConfig(
                    $targetCategoryId,
                    $categoryInfo['source_category_id'],
                    $categoryInfo['recommend_id'],
                    $randomSeed,
                    $sessionId
                );
            }

            if (!empty($searchConfigs['requests'])) {
                $allGoods = $this->parallelSearch($searchConfigs['requests'], $randomSeed);
                $processedGoods = $this->processCategoryGoodsWithBrandPriority($allGoods, self::DEFAULT_LIMIT);
            }
        }

        if (!empty($processedGoods)) {
            $this->addRecommendedOptionIds($targetCategoryId, array_column($processedGoods, 'id'), $sessionId);
        }

        return [
            'category_id'   => $targetCategoryId,
            'goods_list'    => $processedGoods,
            'random_seed'   => $randomSeed,
            'refresh_time'  => time(),
        ];
    }

    public function refreshAllCategories()
    {
        $availableCategories = $this->getAvailableCategories();
        $refreshedCategories = [];

        foreach ($availableCategories as $category) {
            $categoryId = $category['id'];
            $randomSeed = $this->refreshCategoryRandomSeed($categoryId);
            $refreshedCategories[] = [
                'id'       => $categoryId,
                'name'     => $category['name'],
                'new_seed' => $randomSeed
            ];
        }

        $this->cacheDel(self::ALL_CATEGORIES_CACHE_PREFIX . $this->goods_id);

        return [
            'refreshed_categories' => $refreshedCategories,
            'refresh_time'         => time(),
            'message'              => '已刷新' . count($refreshedCategories) . '个分类的推荐'
        ];
    }

    private function getCategoryRandomSeed($categoryId)
    {
        $cacheKey = self::RANDOM_SEED_CACHE_PREFIX . "{$this->goods_id}_{$categoryId}";
        $seed = $this->cacheGet($cacheKey);
        if (!$seed) {
            $seed = $this->generateRandomSeed();
            $this->cacheSet($cacheKey, $seed, self::RANDOM_SEED_EXPIRE);
        }
        return $seed;
    }

    private function refreshCategoryRandomSeed($categoryId)
    {
        $cacheKey = self::RANDOM_SEED_CACHE_PREFIX . "{$this->goods_id}_{$categoryId}";
        $seed = $this->generateRandomSeed();
        $this->cacheSet($cacheKey, $seed, self::RANDOM_SEED_EXPIRE);
        return $seed;
    }

    private function buildRecommendCategorySearchConfig($targetCategoryId, $sourceCategoryId, $recommendId, $randomSeed, $sessionId)
    {
        $alreadyRecommended = $this->getRecommendedOptionIds($targetCategoryId, $sessionId);
        $baseExclude = array_merge($this->currentGoodsOptionIds, $alreadyRecommended);

        $recommend = CategoryRecommend::with('ranges')->find($recommendId);
        if (!$recommend) {
            return [
                'requests' => [[
                    'recommend_category_id' => $targetCategoryId,
                    'filters' => [
                        'category_id' => $targetCategoryId,
                        'id'          => ['operator' => 'NOT IN', 'value' => $baseExclude],
                        'status'      => 1,
                        'enable'      => 1,
                    ],
                    'has_price_match' => false,
                    'price_min'       => null,
                    'price_max'       => null,
                    'priority'        => 2,
                    'is_self_category' => false,
                    'random'          => !is_null($randomSeed),
                    'seed'            => $randomSeed,
                    'seed_index'      => 0,
                ]]
            ];
        }

        $defaultOptions = GoodsOption::where('goods_id', $this->goods_id)
            ->whereHas('goods', function ($query) {
                $query->where('status', 1)->where('type2', 1)->whereNull('deleted_at');
            })
            ->where('is_default', 1)
            ->get();

        $requests = [];
        foreach ($defaultOptions as $defaultOption) {
            $productPrice = floatval($defaultOption->product_price);
            $matchedRange = null;

            foreach ($recommend->ranges as $range) {
                if ($productPrice >= floatval($range->price_min) && $productPrice <= floatval($range->price_max)) {
                    $matchedRange = $range;
                    break;
                }
            }

            $request = [
                'recommend_category_id' => $targetCategoryId,
                'filters' => [
                    'category_id' => $targetCategoryId,
                    'id'          => ['operator' => 'NOT IN', 'value' => $baseExclude],
                    'status'      => 1,
                    'enable'      => 1,
                ],
                'is_self_category' => false,
                'random'           => !is_null($randomSeed),
                'seed'             => $randomSeed,
                'seed_index'       => count($requests),
            ];

            if ($matchedRange) {
                $request['has_price_match'] = true;
                $request['price_min'] = $productPrice * (floatval($matchedRange->ratio_min) / 100);
                $request['price_max'] = $productPrice * (floatval($matchedRange->ratio_max) / 100);
                $request['priority'] = 1;
            } else {
                $request['has_price_match'] = false;
                $request['price_min'] = null;
                $request['price_max'] = null;
                $request['priority'] = 0;
            }

            $requests[] = $request;
        }

        usort($requests, function ($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });

        return ['requests' => $requests];
    }

    private function buildSelfCategorySearchConfig($specCategory, $randomSeed, $sessionId)
    {
        return $this->buildSingleCategorySearchConfig($specCategory, $randomSeed, $sessionId);
    }

    private function buildSingleCategorySearchConfig($specCategory, $randomSeed = null, $sessionId = null)
    {
        $searchRequests = [];
        $categoryId = $specCategory->category_id;

        $alreadyRecommended = $sessionId ? $this->getRecommendedOptionIds($categoryId, $sessionId) : [];
        $baseExclude = array_merge($this->currentGoodsOptionIds, $alreadyRecommended);

        $searchRequests[] = [
            'recommend_category_id' => $categoryId,
            'filters' => [
                'category_id' => $categoryId,
                'id'          => ['operator' => 'NOT IN', 'value' => $baseExclude],
                'status'      => 1,
                'enable'      => 1,
            ],
            'has_price_match' => false,
            'price_min'       => null,
            'price_max'       => null,
            'priority'        => 2,
            'is_self_category' => true
        ];

        $categoryRecommends = CategoryRecommend::where('category_id', $categoryId)
            ->with(['ranges'])
            ->orderBy('sort', 'asc')
            ->get();

        if (!$categoryRecommends->isEmpty()) {
            $defaultOptions = GoodsOption::where('goods_id', $this->goods_id)
                ->whereHas('goods', function ($query) {
                    $query->where('status', 1)->where('type2', 1)->whereNull('deleted_at');
                })
                ->where('is_default', 1)
                ->get();

            foreach ($defaultOptions as $defaultOption) {
                $productPrice = floatval($defaultOption->product_price);

                foreach ($categoryRecommends as $recommend) {
                    if ($recommend->recommend_category_id == $categoryId) {
                        continue;
                    }

                    $hasRanges = !$recommend->ranges->isEmpty();
                    $matchedRange = null;

                    if ($hasRanges) {
                        foreach ($recommend->ranges as $range) {
                            if ($productPrice >= floatval($range->price_min) && $productPrice <= floatval($range->price_max)) {
                                $matchedRange = $range;
                                break;
                            }
                        }
                    }

                    $request = [
                        'recommend_category_id' => $recommend->recommend_category_id,
                        'filters' => [
                            'category_id' => $recommend->recommend_category_id,
                            'id'          => ['operator' => 'NOT IN', 'value' => $baseExclude],
                            'status'      => 1,
                            'enable'      => 1,
                        ],
                        'is_self_category' => false
                    ];

                    if ($hasRanges && $matchedRange) {
                        $request['has_price_match'] = true;
                        $request['price_min'] = $productPrice * (floatval($matchedRange->ratio_min) / 100);
                        $request['price_max'] = $productPrice * (floatval($matchedRange->ratio_max) / 100);
                        $request['priority'] = 1;
                    } else {
                        $request['has_price_match'] = false;
                        $request['price_min'] = null;
                        $request['price_max'] = null;
                        $request['priority'] = 0;
                    }

                    $request['random'] = !is_null($randomSeed);
                    $request['seed'] = $randomSeed;
                    $request['seed_index'] = count($searchRequests);
                    $searchRequests[] = $request;
                }
            }
        }

        usort($searchRequests, function ($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });

        return ['requests' => $searchRequests];
    }


    /**
     * 处理商品并采用品牌优先排序
     */
    private function processCategoryGoodsWithBrandPriority($allGoods, $limit)
    {
        // 去重并再次排除自身商品
        $uniqueGoods = [];
        foreach ($allGoods as $goods) {
            if ($goods['goods_id'] == $this->goods_id) {
                continue;
            }
            if (!isset($uniqueGoods[$goods['id']])) {
                $uniqueGoods[$goods['id']] = $goods;
            }
        }

     

        // 获取品牌ID字段（假设索引中存在 brand_id）
        $sameBrand = [];
        $otherBrand = [];
        foreach ($uniqueGoods as $goods) {
            $brandId = $goods['supplier_id'] ?? 0;
            if ($brandId == $this->currentBrandId && $this->currentBrandId > 0) {
                $sameBrand[] = $goods;
            } else {
                $otherBrand[] = $goods;
            }
        }

        // 内部再按价格区间分组
        $sortGroups = function ($goodsList) {
            $inRange = [];
            $outRange = [];
            foreach ($goodsList as $goods) {
                if (
                    $goods['has_price_match'] &&
                    $goods['price_min'] !== null &&
                    $goods['price_max'] !== null
                ) {
                    $price = floatval($goods['price'] ?? 0);
                    if ($price >= $goods['price_min'] && $price <= $goods['price_max']) {
                        $inRange[] = $goods;
                        continue;
                    }
                }
                $outRange[] = $goods;
            }
            return array_merge($inRange, $outRange);
        };

        $sortedGoods = array_merge(
            $sortGroups($sameBrand),
            $sortGroups($otherBrand)
        );

       

        $limitedGoods = array_slice($sortedGoods, 0, $limit);

        $formattedGoods = [];
        foreach ($limitedGoods as $goods) {
            $formattedGoods[] = $this->formatSingleGoods($goods);
        }
  
        return $formattedGoods;
    }

    private function parallelSearch($searchRequests, $randomSeed = null)
    {
        $allGoods = [];

        if (empty($searchRequests)) {
            return [];
        }

        $chunks = array_chunk($searchRequests, 10);

        foreach ($chunks as $chunkIndex => $chunk) {
            $searchQueries = [];
            $requestMap = [];

            foreach ($chunk as $reqIndex => $request) {
                $filters = $this->buildFilterArray($request['filters']);

                $searchQuery = (new SearchQuery())
                    ->setQuery('')
                    ->setFilter($filters)
                    ->setLimit(self::MAX_SEARCH_LIMIT)
                    ->setOffset(0);

                if ($request['random']) {
                    // 使用独立种子避免不同请求排序完全一致
                    $seedValue = hexdec(substr($request['seed'], 0, 8)) + ($request['seed_index'] ?? 0);
                    $sortConfig = $this->getRandomSortConfig($seedValue, $chunkIndex + 1);
                    $sortRules = [
                        "{$sortConfig['main']['field']}:{$sortConfig['main']['order']}",
                        "{$sortConfig['secondary']['field']}:{$sortConfig['secondary']['order']}",
                        "{$sortConfig['tertiary']['field']}:{$sortConfig['tertiary']['order']}"
                    ];
                    $searchQuery->setSort($sortRules);
                } else {
                    if ($request['has_price_match']) {
                        $searchQuery->setSort(['price:asc']);
                    } else {
                        $searchQuery->setSort(['best_category_final_sort:asc']);
                    }
                }

                $searchQueries[] = $searchQuery;

                $requestMap[count($searchQueries) - 1] = [
                    'recommend_category_id' => $request['recommend_category_id'],
                    'has_price_match' => $request['has_price_match'],
                    'price_min' => $request['price_min'],
                    'price_max' => $request['price_max']
                ];
            }

            try {
                if (!empty($searchQueries)) {
                    $results = $this->client->multiSearch($searchQueries);

                    if (isset($results['results'])) {
                        foreach ($results['results'] as $idx => $result) {
                            if (isset($result['hits']) && isset($requestMap[$idx])) {
                                $requestInfo = $requestMap[$idx];

                                foreach ($result['hits'] as $hit) {
                                    $hit['recommend_category_id'] = $requestInfo['recommend_category_id'];
                                    $hit['has_price_match'] = $requestInfo['has_price_match'];
                                    $hit['price_min'] = $requestInfo['price_min'];
                                    $hit['price_max'] = $requestInfo['price_max'];
                                    $allGoods[] = $hit;
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Multi-search failed for chunk ' . $chunkIndex . ': ' . $e->getMessage());
                $allGoods = array_merge($allGoods, $this->serialSearchFallback($chunk));
            }
        }

        return $allGoods;
    }

    private function buildFilterArray($filters)
    {
        $filterArray = [];

        foreach ($filters as $key => $value) {
            if (empty($value)) continue;

            if ($key == 'id') {
                if (isset($value['operator']) && isset($value['value'])) {
                    $op = strtoupper($value['operator']);
                    $ids = (array) $value['value'];
                    if (empty($ids)) continue;

                    if ($op == '!=' || $op == 'NOT IN') {
                        if ($op == '!=') {
                            $filterArray[] = "id != {$ids[0]}";
                        } else {
                            $idList = implode(',', $ids);
                            $filterArray[] = "id NOT IN [{$idList}]";
                        }
                    } else {
                        $idList = implode(',', $ids);
                        $filterArray[] = "id IN [{$idList}]";
                    }
                }
            } elseif (is_array($value)) {
                if (!empty($value)) {
                    $values = array_map(function ($item) {
                        return is_string($item) ? "'{$item}'" : $item;
                    }, $value);
                    $filterArray[] = "{$key} IN [" . implode(',', $values) . "]";
                }
            } else {
                $filterArray[] = "{$key} = " . (is_string($value) ? "'{$value}'" : $value);
            }
        }

        return $filterArray;
    }

    private function serialSearchFallback($requests)
    {
        $goods = [];

        foreach ($requests as $request) {
            try {
                $filters = $this->buildFilterArray($request['filters']);

                $options = [
                    'filter' => $filters,
                    'limit' => self::MAX_SEARCH_LIMIT
                ];

                if ($request['random']) {
                    $seedValue = hexdec(substr($request['seed'], 0, 8)) + ($request['seed_index'] ?? 0);
                    $sortConfig = $this->getRandomSortConfig($seedValue, 1);
                    $options['sort'] = [
                        "{$sortConfig['main']['field']}:{$sortConfig['main']['order']}",
                        "{$sortConfig['secondary']['field']}:{$sortConfig['secondary']['order']}",
                        "{$sortConfig['tertiary']['field']}:{$sortConfig['tertiary']['order']}"
                    ];
                } else {
                    $options['sort'] = $request['has_price_match'] ? ['price:asc'] : ['best_category_final_sort:asc'];
                }

                $result = $this->index->search('', $options);
                $hits = $result->getHits();

                foreach ($hits as $hit) {
                    $hit['recommend_category_id'] = $request['recommend_category_id'];
                    $hit['has_price_match'] = $request['has_price_match'];
                    $hit['price_min'] = $request['price_min'];
                    $hit['price_max'] = $request['price_max'];
                    $goods[] = $hit;
                }
            } catch (\Exception $e) {
                Log::error('Serial search failed: ' . $e->getMessage());
            }
        }

        return $goods;
    }

    private function getRandomSortConfig($seed, $page)
    {
        $sortFields = [
            'price',
            'id',
            'show_sales',
            'comment_num',
            'created_at',
            'display_order',
            'category_sort',
            'option_order_sort',
            'best_category_final_sort',
            'best_category_last_sort',
            'best_category_sort',
            'best_category_two_last_sort'
        ];

        $sortOrders = ['asc', 'desc'];
        $combinedSeed = $seed + $page * 100;

        $mainFieldIndex = $combinedSeed % count($sortFields);
        $mainOrderIndex = floor($combinedSeed / count($sortFields)) % count($sortOrders);

        $remainingFields = array_values(array_diff_key($sortFields, [$mainFieldIndex => $sortFields[$mainFieldIndex]]));
        $secondaryFieldIndex = floor($combinedSeed / (count($sortFields) * count($sortOrders))) % count($remainingFields);
        $secondaryOrderIndex = floor($combinedSeed / (count($sortFields) * count($sortOrders) * count($remainingFields))) % count($sortOrders);

        return [
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

    private function formatSingleGoods($goods)
    {
        return [
            'id' => $goods['id'],
            'goods_id' => $goods['goods_id'],
            'title' => $goods['goods_title'] . (isset($goods['option_title']) ? ' ' . explode("+", $goods['option_title'])[0] : ''),
            'thumb' => yz_tomedia($goods['thumb']),
            'price' => $goods['price'],
            'min_price' => $goods['price'],
            'max_price' => $goods['price'],
            'is_stock' => $goods['is_stock'] ?? 0,
            'is_hot' => $goods['is_hot'] ?? 0,
            'is_discount' => $goods['is_discount'] ?? 0,
            'in_price_range' => $goods['has_price_match'] &&
                isset($goods['price_min']) &&
                isset($goods['price_max']) &&
                floatval($goods['price'] ?? 0) >= floatval($goods['price_min']) &&
                floatval($goods['price'] ?? 0) <= floatval($goods['price_max'])
        ];
    }

    private function emptyCategoryResponse($categoryId)
    {
        return [
            'category_id' => $categoryId,
            'category_name' => '',
            'goods_list' => [],
            'refresh_time' => time()
        ];
    }

    private function emptyResponse()
    {
        return [
            'current_category' => null,
            'goods_list' => [],
            'available_categories' => [],
            'carousel_info' => [
                'has_next' => false,
                'next_hint' => '下一个推荐分类',
                'can_refresh' => false,
                'refresh_hint' => '刷新推荐'
            ]
        ];
    }

    private function generateRandomSeed()
    {
        return md5(uniqid(mt_rand(), true) . microtime() . $this->goods_id . time());
    }
}
