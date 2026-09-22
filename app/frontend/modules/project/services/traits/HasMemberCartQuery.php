<?php

namespace app\frontend\modules\project\services\traits;

use app\frontend\modules\project\models\Floors;
use app\frontend\modules\project\models\FloorSpace;
use Illuminate\Support\Facades\DB;

trait HasMemberCartQuery
{
    /**
     * 获取项目购物车商品总价（按 project_id 分组）
     * 用于 getList 类方法中计算项目预算金额
     *
     * @param array $ids 项目 ID 列表
     * @return \Illuminate\Support\Collection keyBy('project_id')
     */
    protected function getMemberCartTotal(array $ids): \Illuminate\Support\Collection
    {
        $validFloors = Floors::whereIn('project_id', $ids)->pluck('id')->toArray();
        $validSpaces = FloorSpace::whereIn('project_id', $ids)->pluck('id')->toArray();

        return DB::table('yz_member_cart as c')
            ->join('yz_goods_option as o', 'o.id', '=', 'c.option_id')
            ->whereIn('c.project_id', $ids)
            ->where(function ($query) use ($validFloors) {
                $query->whereIn('c.floor_id', $validFloors);
            })
            ->where(function ($query) use ($validSpaces) {
                $query->whereIn('c.space_id', $validSpaces);
            })
            ->whereNull('c.deleted_at')
            ->selectRaw('ims_c.project_id, SUM(ims_o.product_price * ims_c.total) as total_price')
            ->groupBy('c.project_id')
            ->get()
            ->keyBy('project_id');
    }
}
