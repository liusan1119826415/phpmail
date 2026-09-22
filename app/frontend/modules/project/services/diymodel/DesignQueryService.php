<?php

namespace app\frontend\modules\project\services\diymodel;

use app\common\models\goods\GoodsOptionModel;
use app\common\models\GoodsSpecItem;
use app\common\models\project\CloudDesign;
use app\common\models\project\Project;
use app\common\models\MemberCart;
use app\common\models\project\Issue;
use app\common\models\project\IssueOptions;
use app\frontend\models\GoodsOption;
use app\frontend\modules\coupon\models\Goods;
use app\frontend\modules\member\models\MemberFavorite;
use app\frontend\modules\goods\models\Brand;
use app\frontend\modules\project\models\SearchSupplier;
use app\common\models\Address;
use app\frontend\modules\project\models\CaseModel;
use Overtrue\Pinyin\Pinyin;

class DesignQueryService
{
    protected DesignGoodsService $goodsService;

    public function __construct(DesignGoodsService $goodsService)
    {
        $this->goodsService = $goodsService;
    }

    /**
     * 检测是否覆盖
     */
    public function checkOverlap(int $project_id): array
    {
        $design = CloudDesign::select('id', 'name', 'project_id')->find($project_id);
        if (!$design->project_id) {
            return ['check_status' => 0];
        }
        $project = Project::find($design->project_id);
        if ($project->order_status == 1) {
            return ['check_status' => 2];
        }

        $result = MemberCart::where('project_id', $design->project_id)->exists();
        return ['check_status' => $result ? 1 : 0];
    }

    /**
     * 查询商品状态
     */
    public function getGoodsStatusAll(array $goodsIds): array
    {
        $data = Goods::select('id', 'status')->whereIn('id', $goodsIds)->get()->toArray();
        return $data;
    }

    /**
     * 获取方案详情
     */
    public function getDesignDetail($designId): array
    {
        $design = CloudDesign::select('id', 'name', 'thumbnail', 'design_data')->where('id', $designId)->first();

        if (!$design) {
            return [];
        }

        return $design->toArray();
    }

    /**
     * 获取系列产品
     */
    public function seriesGoods(int $goods_id): array
    {
        $goodsData = $this->goodsService->getGoodsData($goods_id);
        return $goodsData;
    }

    /**
     * 获取关联产品（已整合到系列产品中）
     */
    public function relatedProducts(int $goods_id): array
    {
        $seriesGoods = $this->goodsService->getGoodsData($goods_id);
        return ['seriesGoods' => $seriesGoods, 'relatedGoods' => []];
    }

    /**
     * 获取我的收藏
     */
    public function getMyCollect(): array
    {
        $member_id = \YunShop::app()->getMemberId();

        // 1. 查询用户的所有收藏记录（包含 mirror_type）
        $favorites = MemberFavorite::where("member_id", $member_id)
            ->orderBy('created_at', 'desc')
            ->get(['option_id', 'mirror_type', 'current_goods_id', 'created_at']);

        if ($favorites->isEmpty()) {
            return ['data' => [], 'current_page' => 1, 'total' => 0];
        }

        // 合并同一 option_id 下的左右镜像收藏
        $mergedFavorites = collect();
        $grouped = $favorites->groupBy('option_id');
        foreach ($grouped as $optionId => $items) {
            $hasLeft = $items->contains('mirror_type', 1);
            $hasRight = $items->contains('mirror_type', 2);
            if ($hasLeft && $hasRight) {
                $keep = $items->sortByDesc('created_at')->first();
                $mergedFavorites->push($keep);
            } else {
                foreach ($items as $item) {
                    $mergedFavorites->push($item);
                }
            }
        }
        $favorites = $mergedFavorites->sortByDesc('created_at')->values();

        // 2. 按 option_id 分组
        $optionIds = $favorites->pluck('option_id')->unique()->values()->toArray();

        // 3. 批量查询 GoodsOption 基础信息
        $goodsOptions = GoodsOption::select(
            "id", "goods_id", "supp_id", "title", "thumb", "product_price", "cad_plan_model",
            "length", "width", "height", "d3ModelUrl_weld", "thumb3dModelUrl", "spec_item_id",
            "block_name", "modelType", "specs", "singleType as single", "structure", "mirror_enable"
        )
            ->with([
                'specOne' => function ($q) {
                    $q->select("id", "title");
                },
                'goods'    => function ($q) {
                    $q->select("id", "title", "lead_time", "status");
                },
                'beLongsToSupplier' => function ($q) {
                    $q->select("id", "store_name");
                }
            ])
            ->whereIn('id', $optionIds)
            ->get()
            ->keyBy('id');

        // 4. 收集关联数据
        $specItemIds = $goodsOptions->pluck('spec_item_id')->filter()->unique()->toArray();
        $optionIdsForModel = $goodsOptions->keys()->toArray();

        $specItems = GoodsSpecItem::whereIn('id', $specItemIds)->pluck('productType', 'id')->toArray();
        $modelParams = $this->goodsService->getOptionModels($optionIdsForModel);
        $parentModels = $this->goodsService->getAllOptionParentModels($optionIdsForModel, $modelParams);

        // 5. 构建分页数据
        $page = request()->input('page', 1);
        $perPage = 20;
        $total = $favorites->count();
        $paginatedFavorites = $favorites->forPage($page, $perPage);

        $resultData = [];
        foreach ($paginatedFavorites as $fav) {
            $optionId = $fav->option_id;
            $mirror_type = $fav->mirror_type;

            $item = $goodsOptions->get($optionId);
            if (!$item) {
                continue;
            }

            $item = clone $item;
            $item->mirror_type_in_favorite = $mirror_type;

            if ($mirror_type == 0) {
                $collect_data = ['status' => 1, 'message' => '商品已收藏', 'mirror_type' => 0];
            } elseif ($mirror_type == 1) {
                $collect_data = ['status' => 1, 'message' => '商品已收藏（左镜像）', 'mirror_type' => 1];
            } else {
                $collect_data = ['status' => 1, 'message' => '商品已收藏（右镜像）', 'mirror_type' => 2];
            }
            $item['is_favorite'] = $collect_data;

            $item['thumb'] = yz_tomedia($item->thumb ?? '');
            $item['cad_plan_model'] = yz_tomedia($item->cad_plan_model ?? '');
            $item['price'] = ceil($item->product_price);
            $titleParts = explode("+", $item->title);
            $item['option_last_title'] = end($titleParts);
            $item['title'] = $item->goods->title . explode("+", $item->title)[0] . ($mirror_type == 1 ? '(左)' : ($mirror_type == 2 ? '(右)' : ''));
            $item['d3ModelUrl_weld'] = yz_tomedia($item->d3ModelUrl_weld ?? '');
            $item['thumb3dModelUrl'] = yz_tomedia($item['thumb3dModelUrl'] ?? '');
            $item['modelSlim'] = $item->modelSlim ? unserialize($item->modelSlim) : [];
            $item['model_data'] = array_values($modelParams[$optionId] ?? []);
            $item['model_chilren_data'] = $parentModels[$item->specs] ?? [];
            $item['productType'] = $specItems[$item->spec_item_id] ?? null;
            $item['lead_time'] = $item->goods->lead_time ?? '';
            $item['mirror_enable'] = $item->mirror_enable;
            $item['status'] = $item->goods->status;

            $item['current_goods_id'] = $fav->current_goods_id;

            $resultData[] = $item;
        }

        // 6. 构造分页返回格式
        return [
            'current_page' => $page,
            'data' => $resultData,
            'total' => $total,
            'per_page' => $perPage,
            'last_page' => ceil($total / $perPage),
        ];
    }

    /**
     * 获取产品品类条件
     */
    public function getSearchCategory(): array
    {
        $brand = Brand::select("id", "name")->get()->toArray();
        return $brand;
    }

    /**
     * 获取查询条件产地
     */
    public function getSearchPlace(): array
    {
        $cityIds = SearchSupplier::where('status', 1)->where('role_id', 1)->where('enable', 1)->pluck("province_id")->all();
        $city_list = Address::select("id", "areaname")->whereIn("id", $cityIds)->get()->toArray();

        if ($city_list) {
            $pinyin = new Pinyin();
            foreach ($city_list as $k => $item) {
                $firstChar = mb_substr($item['areaname'], 0, 1);
                $city_list[$k]['first_letter'] = strtoupper($pinyin->abbr($firstChar));
            }
        }
        return $city_list;
    }

    /**
     * 获取推荐行业案例
     */
    public function getRecommendIndustry(): array
    {
        $query = CaseModel::select("id", "title", "favorites_count")->where('is_suggested', 1)->get();
        $query->transform(function ($item) {
            $item->url = yzWebFullUrlV2("case/" . $item->id);
            return $item;
        });
        return $query->toArray();
    }

    /**
     * 获取反馈问题list
     */
    public function getIssueOptions(): array
    {
        $query = IssueOptions::select("id", "title", "parent_id")->where('status', 1)->with(['children' => function ($query) {
            $query->select("id", "title", "parent_id")->where('status', 1);
        }])->where('parent_id', 0)->get();

        return $query->toArray();
    }

    /**
     * 提交反馈问题
     */
    public function submitIssue(array $data): bool
    {
        $issue = new Issue();
        $data['member_id'] = \YunShop::app()->getMemberId();
        $data['content'] = htmlspecialchars($data['content']);
        $data['issue_id'] = $data['issue_id'];
        $data['issue_parent'] = $data['issue_parent'];
        $data['thumb_url'] = $data['thumb_url'] ?? '';
        $data['file_url'] = $data['file_url'] ?? '';
        $issue->fill($data);
        return $issue->save();
    }
}
