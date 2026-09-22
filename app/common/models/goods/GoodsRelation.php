<?php


namespace app\common\models\goods;

use app\common\models\BaseModel;
use app\common\models\Goods;
class GoodsRelation extends BaseModel
{
    protected $table = 'yz_goods_relations';

    protected $guarded = [''];


    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * 获取关联的商品信息
     */
    public function goods()
    {
        return $this->belongsTo(Goods::class, 'goods_id');
    }

    /**
     * 获取被关联的商品信息
     */
    public function relatedGoodsInfo()
    {
        return $this->belongsTo(Goods::class, 'related_goods_id');
    }


    /**
     * 关联商品
     */
    // app/Models/GoodsRelation.php
    public static function relateToGoods($goods_id, $newRelatedGoodsIds)
    {
        // 获取当前已存在的关联
        $existingRelations = GoodsRelation::where('goods_id', $goods_id)
            ->orWhere('related_goods_id', $goods_id)
            ->get();

        // 提取已关联的商品ID（包括正向和反向）
        $existingRelatedIds = $existingRelations->map(function ($relation) use ($goods_id) {
            return $relation->goods_id == $goods_id
                ? $relation->related_goods_id
                : $relation->goods_id;
        })->unique()->values()->toArray();

        // 需要删除的关联（存在于数据库但不在新数据中）
        $idsToRemove = array_diff($existingRelatedIds, $newRelatedGoodsIds);

        // 需要添加的关联（存在于新数据但不在数据库中）
        $idsToAdd = array_diff($newRelatedGoodsIds, $existingRelatedIds);

        // 删除不再需要的关联
        if (!empty($idsToRemove)) {
            GoodsRelation::where(function($query) use ($goods_id, $idsToRemove) {
                $query->where('goods_id', $goods_id)
                    ->whereIn('related_goods_id', $idsToRemove);
            })
                ->orWhere(function($query) use ($goods_id, $idsToRemove) {
                    $query->where('related_goods_id', $goods_id)
                        ->whereIn('goods_id', $idsToRemove);
                })
                ->delete();
        }

        // 添加新的关联
        foreach ($idsToAdd as $relatedGoodsId) {
            if ($goods_id != $relatedGoodsId) {
                // 检查商品是否存在
                if (Goods::where('id', $relatedGoodsId)->exists()) {
                    GoodsRelation::create([
                        'goods_id' => $goods_id,
                        'related_goods_id' => $relatedGoodsId,
                    ]);
                }
            }
        }

        return true;
    }

}