<?php

namespace app\frontend\modules\project\infrastructure;

use app\common\exceptions\AppException;
use app\common\models\Address;
use app\frontend\modules\project\repositories\CaseRepositoryInterface;
use app\frontend\modules\project\models\CaseModel;
use app\common\models\industry\CaseLable;
use app\common\models\industry\CaseFavorite;
use Dompdf\Exception;
use Yunshop\Supplier\common\models\SupplierFollow;
use app\frontend\models\Goods;

class CaseRepository implements CaseRepositoryInterface
{


    public function getList(array $search): array
    {


        $case_type = $search['case_type'];
        $supplier_id = $search['supplier_id'];
        $sort = $search['sort'];
        $query = CaseModel::select("id", "title", "thumb", "favorites_count", "supplier_id")->with(['brandCase' => function ($query) {
            $query->select("id", "store_name", "logo");
        }]);
        if ($case_type) {
            $query->where('case_id', $case_type);
        }
        if ($supplier_id) {
            $query->where('supplier_id', $supplier_id);
        }


        if (!empty($search['title'])) {

            $title = $search['title'];
            $terms = [];

            // 简单按2个字符一组分词
            $len = mb_strlen($title, 'UTF-8');
            for ($i = 0; $i < $len - 1; $i++) {
                $term = mb_substr($title, $i, 2, 'UTF-8');
                if (!in_array($term, ['', ' '])) {
                    $terms[] = $term;
                }
            }

            $query->where(function ($q) use ($terms) {
                foreach ($terms as $term) {
                    $q->orWhere('title', 'like', '%' . $term . '%');
                }
            });

        }


        if ($sort == 1) {
            //最热
            $by = "favorites_count";
        } elseif ($sort == 2) {
            $by = "id";
        } elseif ($sort == 3) {
            //综合排序
            $by = "id";
        } else {
            $by = "id";
        }
        $list = $query->orderBy($by, "desc")->paginate(20);

        $list->transform(function ($item) {
            $item->thumb = yz_tomedia($item->thumb);
            $item->brandCase->logo = yz_tomedia($item->brandCase->logo);
            return $item;
        });

        return $list->toArray();

    }

    public function getCaseLable(): array
    {
        $data = CaseLable::select("id", "name")->get()->toArray();
        return $data;
    }

    //收藏
    public function toggleFavorite(int $id): bool
    {
        $case_info = CaseModel::find($id);
        if (!$case_info) {
            throw new AppException("案例不存在");
        }
        try {
            $memberId = \YunShop::app()->getMemberId();
            // 检查是否已经收藏
            $favorite = CaseFavorite::where('member_id', $memberId)->where('case_id', $id)->first();

            if ($favorite) {
                // 取消收藏
                $favorite->delete();
                CaseModel::where('id', $id)->decrement('favorites_count');
                return true;
            } else {
                // 添加收藏
                CaseFavorite::create(['member_id' => $memberId, 'case_id' => $id]);
                CaseModel::where('id', $id)->increment('favorites_count');
                return true;
            }
        } catch (Exception $e) {
            throw new AppException("收藏失败");
        }

    }


    //行业详情
    public function detail(int $id): array
    {
        $memberId = \YunShop::app()->getMemberId();
        $case_info = CaseModel::select('id', "supplier_id", "case_id", "title", "thumb", "content", "created_at")
            ->with(['brandCase' => function ($query) {
                $query->select("id", "store_name", "logo", "province_id", "city_id");
            }])->where('id', $id)->first();
        if (!$case_info) {
            throw new AppException("案例不存在");
        }

        $favorite = CaseFavorite::where('member_id', $memberId)->where('case_id', $id)->first();
        $case_info->isFavorite = $favorite ? 1 : 0;
        $case_info->thumb = yz_tomedia($case_info->thumb);

        $case_info->created_date = $case_info->created_at->format('Y-m-d');

        $case_info->brandCase->logo = yz_tomedia($case_info->brandCase->logo);
        $address = Address::whereIn('id', [$case_info->brandCase->province_id, $case_info->brandCase->city_id])->pluck('areaname')->toArray();
        $case_info->brandCase->province_name = $address[0];
        $case_info->brandCase->city_name = $address[1];
        $is_ow = SupplierFollow::where('member_id', $memberId)->where('supplier_id', $case_info->supplier_id)->first();
        $case_info->brandCase->isFollow = $is_ow ? 1 : 0;
        //更多相似案例
        $likeCase = CaseModel::select("id", "title", "thumb", "favorites_count", "supplier_id")->where('case_id', $case_info->case_id)->where('id', '!=', $id)
            ->with([
                'brandCase' => function ($query) {
                    $query->select("id", "store_name", "logo");
                }
            ])
            ->limit(4)
            ->get();
        $likeCase->transform(function ($case) {
            $case->thumb = yz_tomedia($case->thumb);

            if ($case->brandCase) {
                $case->brandCase->logo = yz_tomedia($case->brandCase->logo);
            }

            return $case;
        });
        $case_info->likeCase = $likeCase;
        //相关产品
        $related_products = Goods::select("id", "title", "thumb", "price")
            ->where('supp_id', $case_info->supplier_id)
            ->with([
                'hasManyOptions' => function ($query) {
                    $query->selectRaw('MIN(product_price) as min_price, MAX(product_price) as max_price');
                }
            ])
            ->limit(5)
            ->get();
        $related_products->transform(function ($goods) {
            $goods->thumb = yz_tomedia($goods->thumb);
            $min_price = $goods->goodsOptions->min_price ?: $goods->price; // 如果没有关联的商品选项，则使用原商品价格
            $max_price = $goods->goodsOptions->max_price ?: $goods->price;

            $goods->min_price = $min_price;
            $goods->max_price = $max_price;
            return $goods;
        });
        $case_info->related_products = $related_products;
        return $case_info->toArray();


    }


}