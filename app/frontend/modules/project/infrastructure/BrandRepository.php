<?php

namespace app\frontend\modules\project\infrastructure;

use app\common\exceptions\AppException;
use app\common\exceptions\ShopException;

use app\common\models\Address;
use app\frontend\modules\goods\models\Brand;
use app\frontend\modules\goods\models\Goods;
use app\frontend\modules\project\models\Follow;
use app\frontend\modules\project\models\SearchSupplier;
use app\frontend\modules\project\repositories\BrandRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Yunshop\Supplier\common\models\SupplierFollow;
use Yunshop\Supplier\supplier\models\Slide;
use Yunshop\Supplier\common\models\SupplierCredential;

class BrandRepository implements BrandRepositoryInterface
{


    public function conditions(): array
    {
        ##获取品牌数据
        $brand = Brand::select("id", "name")->get()->toArray();

        $data['brand'] = $brand;

        ##产地数据
        $cityIds = SearchSupplier::where('status', 1)->where('role_id', 1)->where('enable', 1)->pluck("city_id")->all();
        $data['city_list'] = Address::select("id", "areaname")->whereIn("id", $cityIds)->get()->toArray();
        return $data;
    }

    public function search(array $filters): array
    {
        // 获取用户 ID


        $memberId = \YunShop::app()->getMemberId();

        $order_field = request()->order_field;


        $fieldMapping = [
            'all' => 'created_at',
        ];

        $name = $fieldMapping[$order_field['name']] ?? 'display_order';
        $order_by = $order_field['order_by'] == 1 ? "asc" : "desc";
        // 构建主查询
        $query = SearchSupplier::select(
            'id',
            'store_name',
            'logo',
            'province_id',
            'city_id',
            'is_bid',
            'bid_enable'
        )->where('status', 1)->where('enable', 1)->where('role_id', 1)->with([
            'hasManyBrand' => function ($query) use ($filters) {
//                if (!empty($filters['brandIds'])) {
//                    $query->whereIn('brand_id', $filters['brandIds']);
//                }
                $query->select('id', 'brand_id', 'supplier_id')->with(['belongsToBrand' => function ($query) {
                    $query->select("id", "name");
                }]); // 仅获取必要字段
            }
        ]);

        // 应用过滤条件
        $query = $this->applyFilters($query, $filters);

        // 如果过滤条件为空或没有符合条件的品牌，退出并返回空结果
        if (!empty($filters['brandIds'])) {
            $query->whereHas('hasManyBrand', function ($query) use ($filters) {
                $query->whereIn('brand_id', $filters['brandIds']);
            });
        }
        // 批量获取数据，分页查询
        $list = $query->orderBy($name, $order_by)->paginate(20);

        // 提取所有需要的 `province_id` 和 `city_id`，避免 N+1 查询
        $provinceIds = $list->pluck('province_id')->unique()->filter()->toArray();
        $cityIds = $list->pluck('city_id')->unique()->filter()->toArray();
        $supplierIds = $list->pluck('id')->toArray();

        // 一次性获取地址信息
        $addresses = Address::whereIn('id', array_merge($provinceIds, $cityIds))
            ->pluck('areaname', 'id');

        // 一次性获取关注状态
        $follows = SupplierFollow::where('member_id', $memberId)
            ->whereIn('supplier_id', $supplierIds)
            ->pluck('supplier_id')
            ->toArray();

        // 构造最终结果
        $list->transform(function ($item) use ($addresses, $follows) {
            $item->city_name = $addresses[$item->city_id] ?? '';
            $item->province_name = $addresses[$item->province_id] ?? '';
            $item->logo = yz_tomedia($item->logo);
            $item->isFollow = in_array($item->id, $follows) ? 1 : 0;
            return $item;
        });

        // 转换为数组返回
        return $list->toArray();
    }


    //品牌馆详情
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
            throw  new AppException("未找到厂家数据");
        }
        $supplier_data->logo = yz_tomedia($supplier_data->logo);
        $supplier_data->thumb = yz_tomedia($supplier_data->thumb);
        $add_ids = [$supplier_data->province_id, $supplier_data->city_id, $supplier_data->district_id, $supplier_data->street_id];
        $address_info = Address::whereIn('id', $add_ids)->pluck("areaname")->all();
        $supplier_data->address_detail = $address_info[0] . $address_info[1] . $address_info[2] . $address_info[3] . $supplier_data->address;
        $memberId = \YunShop::app()->getMemberId();

        //轮播图数据


        $supplier_data->isFollow = Follow::IsFollow($memberId, $supplier_id) ? 1 : 0;
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
                'pc_thumb' => yz_tomedia($item['pc_thumb']), // 修改为处理后的图片链接
                'pc_link' => $item['pc_link'],
                'display_order' => $item['display_order']
            ];
        })->values()->all();
        return $supplier_data->toArray();
    }

    //品牌馆商品数据
    public function getBrandGoods(int $supplier_id): array
    {
        $goodsList = Goods::select("id", "title", "thumb")->where('type2', 1)->where('supp_id', $supplier_id)->where('status', 1)->orderBy('id', 'desc')->paginate(20)->toArray();
        foreach ($goodsList['data'] as $key => $item) {
            $goodsList['data'][$key]['thumb'] = yz_tomedia($item['thumb']);
        }

        return $goodsList;

    }

    //公司介绍等数据
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


    //我关注的列表

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


    public function follow(int $supplier_id, int $follow_type): bool
    {

        $memberId = \YunShop::app()->getMemberId();
        if ($follow_type == 1) {
            if (Follow::IsFollow($memberId, $supplier_id)) {
                throw  new AppException("已关注,请忽重复关注");
            }
            $create['member_id'] = $memberId;
            $create['supplier_id'] = $supplier_id;
            $res = Follow::create($create);
            if ($res) {
                return true;
            } else {
                throw  new AppException("关注失败");
            }
        } else {
            //取消关注
            $follow = Follow::IsFollow($memberId, $supplier_id);
            if (!$follow) {
                throw  new AppException("未关注，请关注");
            }
            if ($follow->delete()) {
                return true;
            } else {
                throw  new AppException("取消关注失败");
            }
        }

    }


    public function isFollow(int $supplier_id): array
    {
        $memberId = \YunShop::app()->getMemberId();
        if (Follow::IsFollow($memberId, $supplier_id)) {
            $data = array(
                'status' => 1,
                'message' => '已关注'
            );
        } else {
            $data = array(
                'status' => 0,
                'message' => '未关注'
            );
        }

        return $data;

    }


    protected function applyFilters(Builder $query, array $filters): Builder
    {

        if (!empty($filters['cityIds'])) {

            $query->whereIn('city_id', $filters['cityIds']);

        }


        if (!empty($filters['title'])) {
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

            // 按匹配度排序（匹配的词越多，排名越靠前）
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