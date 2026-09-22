<?php

namespace app\frontend\modules\project\services\brand;

use app\common\exceptions\AppException;
use app\common\models\Address;
use app\common\models\GoodsOption;
use app\frontend\modules\goods\models\Brand;
use app\frontend\modules\goods\models\Goods;
use app\frontend\modules\project\models\Follow;
use app\frontend\modules\project\models\SearchSupplier;
use app\frontend\modules\project\services\SearchHistoryService;
use Illuminate\Database\Eloquent\Builder;
use Yunshop\Supplier\common\models\SupplierFollow;
use Yunshop\Supplier\supplier\models\Slide;
use Yunshop\Supplier\common\models\SupplierCredential;

class BrandQueryService
{
    // 关注状态
    const FOLLOW_STATUS_YES = 1;  // 已关注
    const FOLLOW_STATUS_NO = 0;   // 未关注

    // 关注状态文本
    const FOLLOW_STATUS_TEXT = [
        self::FOLLOW_STATUS_YES => '已关注',
        self::FOLLOW_STATUS_NO => '未关注',
    ];

    // 排序字段映射
    const ORDER_FIELD_MAP = [
        'all' => 'created_at',
    ];

    const DEFAULT_ORDER_FIELD = 'display_order';

    /**
     * 筛选条件（品牌、产地）
     */
    public function conditions(): array
    {
        $brand = Brand::select("id", "name")->get()->toArray();

        $data['brand'] = $brand;

        $cityIds = SearchSupplier::where('status', 1)->where('role_id', 1)->where('enable', 1)->pluck("province_id")->all();
        $data['city_list'] = Address::select("id", "areaname")->whereIn("id", $cityIds)->get()->toArray();
        return $data;
    }

    /**
     * 品牌搜索
     */
    public function search(array $filters): array
    {
        $memberId = \YunShop::app()->getMemberId();

        $order_field = request()->order_field;

        $name = self::ORDER_FIELD_MAP[$order_field['name']] ?? self::DEFAULT_ORDER_FIELD;
        $order_by = $order_field['order_by'] == 1 ? "asc" : "desc";

        $query = SearchSupplier::select(
            'id', 'store_name', 'logo', 'province_id', 'city_id', 'is_bid', 'bid_enable'
        )->where('status', 1)->where('enable', 1)->where('role_id', 1)->with([
            'hasManyBrand' => function ($query) {
                $query->select('id', 'brand_id', 'supplier_id')->with(['belongsToBrand' => function ($query) {
                    $query->select("id", "name");
                }]);
            }
        ])->withCount([
            'goods'
        ]);

        $query = $this->applyFilters($query, $filters);

        if (!empty($filters['brandIds'])) {
            $query->whereHas('hasManyBrand', function ($query) use ($filters) {
                $query->whereIn('brand_id', $filters['brandIds']);
            });
        }

        $list = $query->orderBy($name, $order_by)->paginate(20);

        $provinceIds = $list->pluck('province_id')->unique()->filter()->toArray();
        $cityIds = $list->pluck('city_id')->unique()->filter()->toArray();
        $supplierIds = $list->pluck('id')->toArray();

        $addresses = Address::whereIn('id', array_merge($provinceIds, $cityIds))
            ->pluck('areaname', 'id');

        $follows = SupplierFollow::where('member_id', $memberId)
            ->whereIn('supplier_id', $supplierIds)
            ->pluck('supplier_id')
            ->toArray();

        $list->transform(function ($item) use ($addresses, $follows) {
            $item->city_name = $addresses[$item->city_id] ?? '';
            $item->province_name = $addresses[$item->province_id] ?? '';
            $item->logo = yz_tomedia($item->logo);
            $item->isFollow = in_array($item->id, $follows) ? self::FOLLOW_STATUS_YES : self::FOLLOW_STATUS_NO;
            return $item;
        });

        return $list->toArray();
    }

    /**
     * 品牌馆详情
     */
    public function detail(int $supplier_id): array
    {
        $supplier_data = SearchSupplier::select("id", "store_name", "thumb", "logo", "is_bid", "introduction", 'certificate', "province_id", "city_id", "district_id", "street_id", "address")->where('status', 1)->with([
            'hasManyBrand' => function ($query) {
                $query->select('id', 'brand_id', 'supplier_id')->with(['belongsToBrand' => function ($query) {
                    $query->select("id", "name");
                }]);
            }
        ])->where('id', $supplier_id)->first();
        if (!$supplier_data) {
            throw new AppException("未找到厂家数据");
        }
        $supplier_data->logo = yz_tomedia($supplier_data->logo);
        $supplier_data->thumb = yz_tomedia($supplier_data->thumb);
        $add_ids = [$supplier_data->province_id, $supplier_data->city_id, $supplier_data->district_id, $supplier_data->street_id];
        $address_info = Address::whereIn('id', $add_ids)->pluck("areaname")->all();
        $supplier_data->address_detail = $address_info[0] . $address_info[1] . $address_info[2] . $address_info[3] . $supplier_data->address;
        $memberId = \YunShop::app()->getMemberId();

        $supplier_data->isFollow = Follow::IsFollow($memberId, $supplier_id) ? self::FOLLOW_STATUS_YES : self::FOLLOW_STATUS_NO;
        $supplier_data->certificate = collect(unserialize($supplier_data->certificate))->map(function ($item) {
            return [
                "name" => $item['name'],
                "url" => yz_tomedia($item['url'])
            ];
        })->values()->all();

        $slide = Slide::select("id", "slide_name", "pc_thumb", "pc_link", "display_order")->where('supplier_id', $supplier_data->id)->where('enabled', 1)->orderBy('display_order', 'asc')->get()->toArray();
        $supplier_data->slide = collect($slide)->map(function ($item) {
            return [
                'id' => $item['id'],
                'slide_name' => $item['slide_name'],
                'pc_thumb' => yz_tomedia($item['pc_thumb']),
                'pc_link' => $item['pc_link'],
                'display_order' => $item['display_order']
            ];
        })->values()->all();
        return $supplier_data->toArray();
    }

    /**
     * 品牌馆商品数据
     */
    public function getBrandGoods(int $supplier_id): array
    {
        $goodsList = GoodsOption::select("id", "title", "thumb", "goods_id", "product_price", "length", "width", "height", "supp_id")->where('is_default', 1)->whereHas('goods', function ($query) {
            $query->where('status', 1)->where('type2', 1);
        })->where('supp_id', $supplier_id)->with(['goods' => function ($query) {
            $query->select("id", "title", "is_discount", "is_hot", 'status');
        }, 'beLongsToSupplier' => function ($query) {
            $query->select("id", "store_name");
        }])->orderBy('id', 'desc')->paginate(20)->toArray();
        foreach ($goodsList['data'] as $key => $item) {
            $goodsList['data'][$key]['price'] = $item['product_price'];
            $goodsList['data'][$key]['status'] = $item['goods']['status'];

            $goodsList['data'][$key]['option_first_title'] = explode("+", $item['title'])[0];

            $goodsList['data'][$key]['thumb'] = yz_tomedia($item['thumb']);
            $goodsList['data'][$key]['title'] = $item['goods']['title'] . explode("+", $item['title'])[0];
            $goodsList['data'][$key]['goods_title'] = $item['goods']['title'];
        }

        return $goodsList;
    }

    /**
     * 公司介绍等数据
     */
    public function getBrandOther(int $supplier_id, int $other_type): array
    {
        $other_list = SupplierCredential::select("id", "type", "material_type", "title", "url", "thumb")
            ->where('type', $other_type)
            ->where('supplier_id', $supplier_id)
            ->orderBy('id', 'desc')->paginate(20)->toArray();
        foreach ($other_list['data'] as $key => $item) {
            $other_list['data'][$key]['thumb'] = yz_tomedia($item['thumb']);
            $other_list['data'][$key]['url'] = $item['url'] ? collect(unserialize($item['url']))->map(function ($url) {
                return yz_tomedia($url);
            })->toArray() : [];
        }
        return $other_list;
    }

    /**
     * 我关注的列表
     */
    public function myFollow(): array
    {
        $memberId = \YunShop::app()->getMemberId();
        $list = Follow::getMyFollow($memberId);
        $list = $list->paginate(20);
        $list->transform(function ($item) {
            $item->logo = yz_tomedia($item->logo);
            return $item;
        });
        return $list->toArray();
    }

    /**
     * 查询是否关注
     */
    public function isFollow(int $supplier_id): array
    {
        $memberId = \YunShop::app()->getMemberId();
        if (Follow::IsFollow($memberId, $supplier_id)) {
            $data = [
                'status' => self::FOLLOW_STATUS_YES,
                'message' => self::FOLLOW_STATUS_TEXT[self::FOLLOW_STATUS_YES],
            ];
        } else {
            $data = [
                'status' => self::FOLLOW_STATUS_NO,
                'message' => self::FOLLOW_STATUS_TEXT[self::FOLLOW_STATUS_NO],
            ];
        }

        return $data;
    }

    /**
     * 应用过滤条件
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters['cityIds'])) {
            $query->whereIn('province_id', $filters['cityIds']);
        }

        if (!empty($filters['title'])) {
            $member_id = \YunShop::app()->getMemberId();
            SearchHistoryService::addSearchHistory(2, $filters['title'], $member_id);
            $title = trim($filters['title']);
            $terms = [];

            // 按 2 个字符一组分词
            $len = mb_strlen($title, 'UTF-8');
            for ($i = 0; $i < $len - 1; $i++) {
                $term = mb_substr($title, $i, 2, 'UTF-8');
                if (!in_array($term, ['', ' '])) {
                    $terms[] = $term;
                }
            }

            $query->where(function ($q) use ($terms, $title) {
                // 1. 完全匹配 store_name = 'xxx'（最高优先级）
                $q->orWhere('store_name', $title);

                // 2. 包含完整搜索词（次高优先级）
                $q->orWhere('store_name', 'like', '%' . $title . '%');

                // 3. 分词模糊匹配（最低优先级）
                foreach ($terms as $term) {
                    $q->orWhere('store_name', 'like', '%' . $term . '%');
                }
            });

            // 按匹配度排序
            if (!empty($terms)) {
                $orderByCase = "CASE ";
                foreach ($terms as $term) {
                    $orderByCase .= "WHEN store_name LIKE '%" . addslashes($term) . "%' THEN 1 ";
                }
                $orderByCase .= "ELSE 0 END";

                $query->orderByRaw("
            CASE 
                WHEN store_name = ? THEN 1000               -- 完全匹配，最高分
                WHEN store_name LIKE ? THEN 500            -- 包含完整搜索词，次高分
                ELSE ({$orderByCase})                       -- 匹配的分词数量
            END DESC
        ", [$title, "%{$title}%"]);
            }
        }

        if (isset($filters['is_bid']) && ($filters['is_bid'] !== '' || $filters['is_bid'] === 0)) {
            $query->where('is_bid', $filters['is_bid']);
        }

        if (isset($filters['bid_enable']) && ($filters['bid_enable'] !== '' || $filters['bid_enable'] === 0)) {
            $query->where('bid_enable', $filters['bid_enable']);
        }

        return $query;
    }
}
