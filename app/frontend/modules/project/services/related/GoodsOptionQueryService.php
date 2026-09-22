<?php

namespace app\frontend\modules\project\services\related;
use app\common\models\GoodsOption;
use app\common\models\goods\GoodsRelation;


// ---------- 1. 公共查询服务 ----------
class GoodsOptionQueryService
{
    /**
     * 构建基础的 GoodsOption 查询
     */
    private function baseQuery()
    {
        return GoodsOption::select(
            "id", "goods_id", "supp_id", "title", "thumb", "product_price",
            "cad_plan_model", "length", "width", "height", "d3ModelUrl_weld",
            "thumb3dModelUrl", "spec_item_id", "block_name", "modelType",
            "specs", "singleType as single", "structure", "mirror_enable", "spec_item_id"
        )
        ->with([
            'specOne' => fn($q) => $q->select("id", "title"),
            'goods'   => fn($q) => $q->select("id", "title", "lead_time", "sku", 'status'),
            'beLongsToSupplier' => fn($q) => $q->select("id", "store_name"),
            'specCategory' => fn($q) => $q->where('category_type', 2)
                ->with(['category' => fn($c) => $c->select('id', 'name', 'parent_id')])
        ])
        ->whereNotNull('thumb3dModelUrl')
        ->where('modelType', 0);
    }

    /**
     * 根据 ID 数组获取选项集合
     */
    public function getOptionsByIds(array $ids): \Illuminate\Support\Collection
    {
        if (empty($ids)) return collect();
        return $this->baseQuery()->whereIn('id', $ids)->get();
    }

    /**
     * 获取某商品下的所有主选项（不含关联）
     */
    public function getMainOptionsByGoodsId(int $goodsId): \Illuminate\Support\Collection
    {
        return $this->baseQuery()->where('goods_id', $goodsId)->get();
    }

    /**
     * 获取某商品的所有关联选项 ID（从 goods_relation 表）
     */
    public function getRelatedOptionIds(int $goodsId): array
    {
        return GoodsRelation::where('goods_id', $goodsId)
            ->pluck('related_goods_id')
            ->unique()
            ->toArray();
    }

    /**
     * 获取 3D 模型所需的选项（当前商品 + 关联 spec_item_id 的选项）
     * 用于 GoodsBaseService::getThreeModel
     */
    public function getThreeModelOptions(int $goodsId, array $relatedOptionIds = []): \Illuminate\Support\Collection
    {
        $specItemIds = [];
        if (!empty($relatedOptionIds)) {
            $specItemIds = GoodsOption::whereIn('id', $relatedOptionIds)
                ->where('modelType', 0)
                ->pluck('spec_item_id')
                ->toArray();
        }

        return GoodsOption::where("modelType", 0)->where(function ($query) use ($goodsId, $specItemIds) {
            $query->where('goods_id', $goodsId);
            if (!empty($specItemIds)) {
                $query->orWhereIn('spec_item_id', $specItemIds);
            }
        })->get();
    }
}
