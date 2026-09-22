<?php

namespace app\frontend\modules\project\services\project;

use app\common\exceptions\ShopException;
use app\frontend\models\GoodsOption;
use app\frontend\modules\project\infrastructure\BaseRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * 项目商品数据服务
 * 负责：获取项目商品、推荐商品、整体改价
 */
class ProjectProductService
{
    /**
     * 获取项目商品数据（含缓存）
     */
    public function getProjectData(int $space_id): array
    {
        $baseRepo = app(BaseRepository::class);
        $data = [];
        $data['activate_project'] = $baseRepo->updateActivateCache(\YunShop::app()->getMemberId(), $this->getActiveProjectId());
        if (empty($data['activate_project'])) {
            $data['activate_project'] = [];
        }
        $data['goods_list'] = $this->getProductGoodsV2($space_id);
        $data['lovely_goods_list'] = $this->lovely();
        return $data;
    }

    /**
     * 整体改价
     */
    public function updatePrice(int $project_id, float $ratio): array
    {
        try {
            $baseRepo = app(BaseRepository::class);
            $option_ids = app('OrderManager')->make('MemberCart')->where('project_id', $project_id)->pluck("option_id")->all();
            $updatedRows = GoodsOption::whereIn('id', $option_ids)
                ->update(['market_price' => DB::raw('product_price *' . $ratio)]);
            $data = $baseRepo->updateActivateCache(\YunShop::app()->getMemberId(), $project_id);
            $space_ids = app('OrderManager')->make('MemberCart')->where('project_id', $project_id)->pluck("space_id")->all();
            foreach ($space_ids as $space_id) {
                $baseRepo->removeCacheCart($space_id);
            }
            return ['project_statistics' => $data];
        } catch (\Exception $e) {
            throw new ShopException(config("app.debug") ? $e->getMessage() : "异常失败");
        }
    }

    private function getProductGoodsV2($space_id)
    {
        $member_id = \YunShop::app()->getMemberId();
        $cacheKey = "member_cart_list_{$member_id}_{$space_id}";
        $data = Cache::get($cacheKey);
        if ($data) {
            return $data;
        }

        $baseRepo = app(BaseRepository::class);
        $data = $baseRepo->processProduct($member_id, $space_id);
        Cache::put($cacheKey, $data, 24 * 60 + rand(10, 99));
        return $data;
    }

    private function lovely()
    {
        $lovely_list = \app\frontend\modules\goods\models\Goods::select("id", "title", "thumb")->with(['hasManyOptions' => function ($query) {
            $query->select("id", "goods_id", "product_price");
        }])->limit(20)->get();

        $lovely_list = $lovely_list->map(function ($item) {
            $productPrices = $item->hasManyOptions->pluck('product_price')->filter()->all();
            $min_price = !empty($productPrices) ? min($productPrices) : $item->price;
            $max_price = !empty($productPrices) ? max($productPrices) : $item->price;
            return [
                'id' => $item->id,
                'title' => $item->title,
                'min_price' => $min_price,
                'max_price' => $max_price,
                'thumb' => yz_tomedia($item->thumb),
            ];
        })->all();
        return $lovely_list;
    }

    private function getActiveProjectId()
    {
        return \app\frontend\modules\project\models\Project::where('member_id', \YunShop::app()->getMemberId())
            ->where('activate', 1)
            ->value('id') ?: 0;
    }
}
