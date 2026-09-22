<?php

namespace app\frontend\modules\project\services\goods;

use app\common\exceptions\ShopException;
use app\frontend\models\GoodsOption;
use app\frontend\models\OrderGoods;
use app\frontend\modules\project\models\Project;
use app\common\traits\GoodsOptionTrait;
use Illuminate\Support\Facades\DB;

class GoodsCartService
{
    use GoodsOptionTrait;

    //预览清单
    public function previewList(int $project_id): array
    {

        $memberId = \YunShop::app()->getMemberId();
        //检查是否有订单
        $Project = Project::find($project_id);

        $orderGoods = null;
        if ($Project->order_id) {

            $orderGoods = OrderGoods::select("goods_id", "goods_option_id", "space_id", "price", "goods_option_price")
                ->where('order_main_id', $Project->order_id)
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->goods_id . '_' . $item->goods_option_id . '_' . $item->space_id => $item];
                });
        }
        $data = app('CartContainer')->make('MemberCart')->floor()
            ->where(app('CartContainer')->make('MemberCart')->getTable() . '.member_id', $memberId)
            ->where(app('CartContainer')->make('MemberCart')->getTable() . '.project_id', $project_id)
            ->join('yz_project_floor_space', 'yz_project_floor_space.id', '=', app('CartContainer')->make('MemberCart')->getTable() . '.space_id')
            ->join('yz_project_floors', 'yz_project_floors.id', '=', app('CartContainer')->make('MemberCart')->getTable() . '.floor_id')
            ->orderBy('yz_project_floors.sort', 'asc')
            ->orderBy('yz_project_floor_space.sort', 'asc')
            ->orderBy(app('CartContainer')->make('MemberCart')->getTable() . '.sort', 'asc')
            ->orderBy(app('CartContainer')->make('MemberCart')->getTable() . '.created_at', 'desc')
            ->get()->toArray();

        $result = collect($data)->groupBy('belongs_to_floor.id')->map(function ($floorItems, $floorId) use ($Project, $orderGoods) {
            $floorName = $floorItems->first()['belongs_to_floor']['floor_name'];

            // 按空间分组
            $spaces = collect($floorItems)->groupBy('belongs_to_space.id')->map(function ($spaceItems, $spaceId) use ($Project, $orderGoods) {
                $spaceName = $spaceItems->first()['belongs_to_space']['space_name'];

                $goodsData = $spaceItems->map(function ($item) use ($Project, $orderGoods) {

                    $processedItem = $this->processGoodsOptionData($item, $Project, $orderGoods, 'standard');
                    return $processedItem;
                })->values();
                $spaceTotalPrice = $goodsData->sum('total_price');

                $spaceTotalNum = $goodsData->sum('total');
                return [
                    'space_id' => $spaceId,
                    'space_name' => $spaceName,
                    'goods' => $goodsData,
                    'space_total_price' => $spaceTotalPrice,
                    'space_total_num' => $spaceTotalNum,
                ];
            })->values();
            $floorTotalPrice = $spaces->sum('space_total_price');

            $floorTotalNum = $spaces->sum('space_total_num');
            // 统计该楼层的商品品种数量
            $floorVarieties = $spaces->flatMap(function ($space) {
                return $space['goods'];
            })->unique(function ($item) {
       
                return $item['goods_id'] . '_' . ($item['option_id'] ?? '');

            })->count();
            return [
                'floor_id' => $floorId,
                'floor_name' => $floorName,
                'space' => $spaces,
                'floor_total_price' => $floorTotalPrice,
                'floor_total_num' => $floorTotalNum,
                'floor_varieties' => $floorVarieties,  // 新增：楼层品种数量

            ];
        })->values();
        $projectTotalPrice = $result->sum('floor_total_price');
        $projectTotalNum = $result->sum('floor_total_num');
        // 计算品种总数
        $totalVarieties = $result->sum(function ($floor) {
            return $floor['space']->sum(function ($space) {
                return $space['goods']->unique(function ($item) {
                    // 如果同一商品不同规格算不同品种，用 goods_id + option_id
                    return $item['goods_id'] . '_' . $item['option_id'];
          
                })->count();
            });
        });

        return [
            'project_total_price' => $projectTotalPrice,
            'project_total_num' => $projectTotalNum,
            'total_varieties' => $totalVarieties,
            'data' => $result->toArray()
        ];
    }


    //批量修改单价
    public function updateUnitPrice(array $option_ids, float $price, int $method, int $space_id): array
    {
        try {
            $baseRepo = $this->getBaseRepo();

            if ($method === 2) {
                $updatedRows = GoodsOption::whereIn('id', $option_ids)
                    ->update(['market_price' => DB::raw('product_price *' . $price)]);
            } else {
                // 否则更新为固定值
                $updatedRows = GoodsOption::whereIn('id', $option_ids)
                    ->update(['market_price' => $price]);
            }

            if ($updatedRows > 0) {
                $space_goods_list = $baseRepo->updateCart($space_id);

                $first = app('OrderManager')->make('MemberCart')->where('space_id', $space_id)->first();
                $data = $baseRepo->updateActivateCache(\YunShop::app()->getMemberId(), $first->project_id);
                return ['project_statistics' => $data, 'space_goods_list' => $space_goods_list];
            }
        } catch (\Exception $e) {
            throw new ShopException(config("app.debug") ? $e->getMessage() : "操作失败");
        }
    }


    //替换
    public function updateOption(array $request_data): array
    {
        try {
            $baseRepo = $this->getBaseRepo();

            $cartModel = app('OrderManager')->make('MemberCart')->find($request_data['id']);

            // 检查是否真的需要替换（新参数和原参数是否相同）

            $isSameOption = $this->checkIfSameOption($cartModel, $request_data);

            if ($isSameOption) {
                // 如果规格完全一样，不需要替换，直接返回当前购物车数据
                $space_id = $cartModel->space_id;
                $data = $baseRepo->updateCart($space_id);
                return ['space_goods_list' => $data];
            }

            $space_id = $cartModel->space_id;
            $data['option_id'] = $request_data['option_id'];
            $data['member_id'] = \YunShop::app()->getMemberId();
            $data['goods_id'] = $cartModel->goods_id;
            $data['space_id'] = $cartModel->space_id;
            $data['is_mirrored'] = $request_data['is_mirrored'] ? 1 : 0;

            // 处理components参数
            $request_data['components'] = $request_data['components'];



            $data['components_hash'] = md5(json_encode($request_data['components'], JSON_UNESCAPED_UNICODE));

            // 检查购物车中是否已存在相同的商品（新规格）
            $hasGoodsModel = app('OrderManager')->make('MemberCart')->hasGoodsToMemberCart($data);

            if ($hasGoodsModel) {
                // 如果购物车中已存在相同规格的商品，合并数量
                $hasGoodsModel->components_hash = $data['components_hash'];
                $hasGoodsModel->option_id = $request_data['option_id'];
                $hasGoodsModel->components = json_encode($request_data['components'], JSON_UNESCAPED_UNICODE);
                $hasGoodsModel->total += $cartModel->total; // 合并原购物车项的数量

                $hasGoodsModel->validate();

                if ($hasGoodsModel->update()) {
                    $cartModel->delete(); // 删除原购物车项
                }
            } else {
                // 购物车中没有相同规格的商品，直接修改当前购物车项
                $cartModel->option_id = $request_data['option_id'];
                $cartModel->components_hash = $data['components_hash'];
                $cartModel->is_mirrored = $request_data['is_mirrored'] ? 1 : 0;
                $cartModel->components = json_encode($request_data['components'], JSON_UNESCAPED_UNICODE);
                $cartModel->save();
            }

            $data = $baseRepo->updateCart($space_id);
            $baseRepo->updateActivateCache(\YunShop::app()->getMemberId(), $cartModel->project_id);

            return ['space_goods_list' => $data];
        } catch (\Exception $e) {
            throw new ShopException(config("app.debug") ? $e->getMessage() : "操作失败");
        }
    }

    /**
     * 检查新参数是否与原购物车项参数相同
     */
    private function checkIfSameOption($cartModel, $request_data): bool
    {
        // 比较option_id
        if ($cartModel->option_id != $request_data['option_id']) {
            return false;
        }

        if ($cartModel->is_mirrored != $request_data['is_mirrored']) {
            return false;
        }

        // 处理components并比较
        $requestComponents = $request_data['components'];



        $newComponentsHash = md5(json_encode($requestComponents, JSON_UNESCAPED_UNICODE));

        // 如果原购物车项没有components_hash，从components字段计算
        if (empty($cartModel->components_hash) && !empty($cartModel->components)) {
            $oldComponentsHash = md5($cartModel->components);
        } else {
            $oldComponentsHash = $cartModel->components_hash;
        }

        return $newComponentsHash === $oldComponentsHash;
    }
}
