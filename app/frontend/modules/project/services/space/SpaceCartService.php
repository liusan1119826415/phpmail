<?php

namespace app\frontend\modules\project\services\space;

use app\common\exceptions\AppException;
use app\common\exceptions\ShopException;
use app\frontend\modules\cart\models\MemberCart;
use app\frontend\modules\coupon\models\Goods;
use app\frontend\modules\member\services\MemberCartService;
use app\frontend\modules\project\infrastructure\BaseRepository;
use app\frontend\modules\project\models\Floors;
use app\frontend\modules\project\models\FloorSpace;
use app\frontend\modules\project\models\Project;
use app\frontend\modules\project\services\AssemblyGoodsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * 空间购物车服务
 * 负责：商品加入空间(普通/3D)、修改数量、移动商品、删除商品、定制标记
 */
class SpaceCartService
{
    /**
     * 3D项目加入空间
     */
    public function add3DSpace(array $data): array
    {
        $projectInfo = Project::where('id', $data['project_id'])->where('order_status', 1)->first();
        if ($projectInfo) {
            throw new AppException("项目订单状态已经锁定");
        }

        $project = Project::where('member_id', \YunShop::app()->getMemberId())->where('id', $data['project_id'])->where('activate', 1)->first();
        if (!$project) {
            throw new ShopException('当前项目未激活或不存在！');
        }

        $floors = Floors::where('project_id', $data['project_id'])->where('id', $data['floor_id'])->first();
        if (!$floors) {
            throw new ShopException('未找到项目楼层！');
        }

        $space = FloorSpace::where('project_id', $data['project_id'])->where('floor_id', $data['floor_id'])->where('id', $data['space_id'])->first();
        if (!$space) {
            throw new ShopException('未找到项目空间！');
        }

        $service = new AssemblyGoodsService;

        $is_edit = false;
        $hasGoodsModel = null;
        if ($data['cart_id']) {
            $res = $this->check3DSpace($data);
            $is_edit = $res['is_edit'];
            $hasGoodsModel = $res['memberCart'];
        }
        $result = $service->assembleDwg($data, $is_edit);

        $data = array(
            'member_id' => \YunShop::app()->getMemberId(),
            'uniacid' => \YunShop::app()->uniacid,
            'project_id' => $data['project_id'],
            'space_id' => $data['space_id'],
            'floor_id' => $data['floor_id'],
            'type' => 2,
            'goods_id' => $result['goods_id'],
            'total' => $data['total'],
            'option_id' => $result['option_id'] ?: 0,
            'components' => $data['components'],
            'components_hash' => md5($data['components']),
            'jsonData' => $data['jsonData'],
            'modelData' => $data['modelData'],
            'add_type' => 1,
        );

        $cartModel = app('OrderManager')->make('MemberCart', $data);
        $cart_id = $hasGoodsModel->id;
        $min_buy_limit = 0;

        $baseRepo = app(BaseRepository::class);

        if ($hasGoodsModel) {
            $num = intval($data['total']) ?: 0;
            $hasGoodsModel->total = max($hasGoodsModel->total + $num, $min_buy_limit);
            $hasGoodsModel->components = $data['components'];
            $hasGoodsModel->jsonData = $data['jsonData'];
            $hasGoodsModel->modelData = $data['modelData'];
            $hasGoodsModel->add_type = 1;
            $hasGoodsModel->validate();

            if ($hasGoodsModel->update()) {
                $baseRepo->updateCart($data['space_id']);
                $data = $baseRepo->updateActivateCache(\YunShop::app()->getMemberId(), $data['project_id']);
                return [
                    'cart_id' => $cart_id,
                    'cart_num' => \app\frontend\models\MemberCart::getCartNum(\YunShop::app()->getMemberId()),
                    'project_statistics' => $data
                ];
            }
            throw new ShopException('数据更新失败，请重试！');
        }
        $cartModel->validate();

        $validator = $cartModel->validator($cartModel->getAttributes());
        if ($validator->fails()) {
            throw new ShopException("数据验证失败，加入空间失败！！！");
        } else {
            if ($cartModel->save()) {
                event(new \app\common\events\cart\AddCartEvent($cartModel));
                $baseRepo->updateCart($data['space_id']);
                $data = $baseRepo->updateActivateCache(\YunShop::app()->getMemberId(), $data['project_id']);
                return [
                    'cart_id' => $cartModel->id,
                    'cart_num' => \app\frontend\models\MemberCart::getCartNum(\YunShop::app()->getMemberId()),
                    'project_statistics' => $data
                ];
            } else {
                throw new ShopException("写入出错，加入空间失败！！！");
            }
        }
        throw new ShopException("接收数据出错，加入空间失败!");
    }

    /**
     * 移动购物车项到另一个空间
     */
    public function moveCartItem(array $data): bool
    {
        $cartId = $data['cart_id'] ?? 0;
        $targetSpaceId = $data['space_id'] ?? 0;

        if (!$cartId || !$targetSpaceId) {
            throw new ShopException("参数不完整：需要 cart_id, space_id");
        }
        $member_id = \YunShop::app()->getMemberId();

        $cartItem = MemberCart::find($cartId);
        if (!$cartItem) {
            throw new ShopException("购物车记录不存在");
        }

        if ($targetSpaceId == $cartItem->space_id) {
            throw new ShopException("商品已经是当前空间");
        }

        $targetSpace = FloorSpace::where('id', $targetSpaceId)->first();
        if (!$targetSpace) {
            throw new ShopException("目标空间不存在");
        }

        return DB::transaction(function () use ($cartId, $targetSpaceId, $member_id, $cartItem, $targetSpace) {
            $where['option_id'] = $cartItem->option_id;
            $where['member_id'] = $member_id;
            $where['goods_id'] = $cartItem->goods_id;
            $where['space_id'] = $targetSpaceId;
            $where['components_hash'] = md5($cartItem->components);

            $hasGoodsModel = app('OrderManager')->make('MemberCart')->hasGoodsToMemberCart($where);
            if ($hasGoodsModel) {
                $hasGoodsModel->total = $hasGoodsModel->total + $cartItem->total;
                $hasGoodsModel->validate();

                if ($hasGoodsModel->update()) {
                    $cartItem->delete();
                    $old_space_id = $cartItem->space_id;
                    $cacheKey = "member_cart_list_{$member_id}_{$old_space_id}";
                    Cache::forget($cacheKey);
                }
            } else {
                $cacheKey = "member_cart_list_{$member_id}_{$cartItem->space_id}";
                Cache::forget($cacheKey);
                $cartItem->floor_id = $targetSpace->floor_id;
                $cartItem->space_id = $targetSpaceId;
                $cartItem->save();
            }
            $cacheKey = "member_cart_list_{$member_id}_{$targetSpaceId}";
            Cache::forget($cacheKey);
            Redis::del("user:{$member_id}:active_project_1");
            return true;
        });
    }

    /**
     * 普通商品加入空间
     */
    public function addSpace(array $data): array
    {
        $projectInfo = Project::where('id', $data['project_id'])->where('order_status', 1)->first();
        if ($projectInfo) {
            throw new AppException("项目订单状态已经锁定");
        }
        $goods = Goods::find($data['goods_id']);
        if (!$goods || $goods->status == 0) {
            throw new AppException("商品已下架或者已删除");
        }
        $data['components'] = json_encode($data['components'], JSON_UNESCAPED_UNICODE);
        $data = array(
            'member_id' => \YunShop::app()->getMemberId(),
            'uniacid' => \YunShop::app()->uniacid,
            'project_id' => $data['project_id'],
            'space_id' => $data['space_id'],
            'floor_id' => $data['floor_id'],
            'goods_id' => $data['goods_id'],
            'total' => $data['total'],
            'is_mirrored' => $data['is_mirrored'] ? 1 : 0,
            'option_id' => $data['option_id'] ?: 0,
            'components' => $data['components'],
            'components_hash' => md5($data['components']),
            'add_type' => 1,
        );

        $floors = Floors::where('project_id', $data['project_id'])->where('id', $data['floor_id'])->first();
        if (!$floors) {
            throw new ShopException('未找到项目楼层！');
        }

        $space = FloorSpace::where('project_id', $data['project_id'])->where('floor_id', $data['floor_id'])->where('id', $data['space_id'])->first();
        if (!$space) {
            throw new ShopException('未找到项目空间！');
        }

        $baseRepo = app(BaseRepository::class);

        $cartModel = app('OrderManager')->make('MemberCart', $data);
        $hasGoodsModel = app('OrderManager')->make('MemberCart')->hasGoodsToMemberCart($data);
        $cart_id = $hasGoodsModel['id'];
        $min_buy_limit = 0;
        if ($hasGoodsModel) {
            $num = intval($data['total']) ?: 1;
            $hasGoodsModel->total = max($hasGoodsModel->total + $num, $min_buy_limit);
            $hasGoodsModel->add_type = 1;
            $hasGoodsModel->validate();

            if ($hasGoodsModel->update()) {
                $baseRepo->updateCart($data['space_id']);
                $data = $baseRepo->updateActivateCache(\YunShop::app()->getMemberId(), $data['project_id']);
                return [
                    'cart_id' => $cart_id,
                    'cart_num' => \app\frontend\models\MemberCart::getCartNum(\YunShop::app()->getMemberId()),
                    'project_statistics' => $data
                ];
            }
            throw new ShopException('数据更新失败，请重试！');
        }
        $cartModel->validate();

        $validator = $cartModel->validator($cartModel->getAttributes());
        event(new \app\common\events\cart\AddCartEvent($cartModel->getAttributes()));
        if ($validator->fails()) {
            throw new ShopException("数据验证失败，加入空间失败！！！");
        } else {
            if ($cartModel->save()) {
                event(new \app\common\events\cart\AddCartEvent($cartModel));
                $baseRepo->updateCart($data['space_id']);
                $data = $baseRepo->updateActivateCache(\YunShop::app()->getMemberId(), $data['project_id']);
                return [
                    'cart_id' => $cartModel->id,
                    'cart_num' => \app\frontend\models\MemberCart::getCartNum(\YunShop::app()->getMemberId()),
                    'project_statistics' => $data
                ];
            } else {
                throw new ShopException("写入出错，加入空间失败！！！");
            }
        }
        throw new ShopException("接收数据出错，加入空间失败!");
    }

    /**
     * 修改空间商品数量
     */
    public function updateSpaceNum(int $cartId, int $num): array
    {
        if (is_null($cartId)) {
            $cartId = $this->getMemberCarId();
        }

        $baseRepo = app(BaseRepository::class);

        if ($cartId && $num) {
            $cartModel = app('OrderManager')->make('MemberCart')->find($cartId);
            if ($cartModel) {
                $projectInfo = Project::where('id', $cartModel->project_id)->where('order_status', 1)->first();
                if ($projectInfo) {
                    throw new AppException("项目订单状态已经锁定");
                }

                $goods = Goods::find($cartModel->goods_id);
                if (!$goods || $goods->status == 0) {
                    throw new AppException("商品已下架或者已删除");
                }

                $min_buy_limit = 0;
                $goodsPrivilege = $cartModel->goods->hasOnePrivilege;
                if (isset($goodsPrivilege) && $goodsPrivilege->min_buy_limit) {
                    if ($cartModel->isOption() && $goodsPrivilege->option_id_array) {
                        if (in_array($cartModel->option_id, $goodsPrivilege->option_id_array)) {
                            $min_buy_limit = $goodsPrivilege->min_buy_limit;
                        }
                    } else {
                        $min_buy_limit = $goodsPrivilege->min_buy_limit;
                    }
                }

                $cartModel->total = $num;

                if ($cartModel->total < 1 || $cartModel->total < $min_buy_limit) {
                    $result = MemberCartService::clearCartByIds([$cartModel->id]);
                    if ($result) {
                        $baseRepo->updateCart($cartModel->space_id);
                        $data = $baseRepo->updateActivateCache(\YunShop::app()->getMemberId(), $cartModel->project_id);
                        return ['project_statistics' => $data];
                    }
                }
                $cartModel->validate();
                if ($cartModel->update()) {
                    $baseRepo->updateCart($cartModel->space_id);
                    $data = $baseRepo->updateActivateCache(\YunShop::app()->getMemberId(), $cartModel->project_id);
                    return ['project_statistics' => $data];
                }
            }
        }

        throw new ShopException('未获取到数据，请重试！');
    }

    /**
     * 删除空间商品
     */
    public function destroy(array $ids, int $space_id): array
    {
        $space = FloorSpace::find($space_id);
        if (empty($ids)) {
            $ids = $this->getMemberCarId();
        }

        $baseRepo = app(BaseRepository::class);
        $result = MemberCartService::clearCartByIdsV2($ids);
        $baseRepo->updateCart($space_id);
        $data = $baseRepo->updateActivateCache(\YunShop::app()->getMemberId(), $space->project_id);
        if ($result) {
            return ['project_statistics' => $data];
        }
        throw new AppException('写入出错，移除空间失败！');
    }

    /**
     * 定制标记
     */
    public function customized(): bool
    {
        try {
            $memberId = \YunShop::app()->getMemberId();
            $id = request()->input('id');
            $customized_remark = request()->input('customized_remark');
            $is_customized = request()->input('is_customized');
            $member_cart = app('OrderManager')->make('MemberCart')->where('id', $id)->first();
            if (!$member_cart) {
                throw new AppException("空间数据不存在");
            }
            $member_cart->is_customized = $is_customized;
            $member_cart->customized_remark = $customized_remark;
            $member_cart->save();
            $cacheKey = "member_cart_list_{$memberId}_{$member_cart->space_id}";
            Cache::forget($cacheKey);
            return true;
        } catch (\Exception $e) {
            throw new AppException("定制失败");
        }
    }

    /**
     * 检查3D空间商品变更
     */
    private function check3DSpace($data): array
    {
        $memberCart = app('OrderManager')->make('MemberCart')->where('id', $data['cart_id'])->first();

        $goods = Goods::find($memberCart->goods_id);
        if ($goods->productType == 5) {
            if (serialize($data['optionData']) == $goods->old_option) {
                return ['is_edit' => 0, 'memberCart' => $memberCart];
            } else {
                return ['is_edit' => 1, 'memberCart' => $memberCart];
            }
        } else {
            $jsonData = json_decode($memberCart->jsonData, true);
            $jsonData1 = json_decode($data['jsonData'], true);
            $blocknames = array_column($jsonData, 'blockname');
            $blockname_two = array_column($jsonData1, 'blockname');

            if ($blocknames === $blockname_two) {
                return ['is_edit' => 1, 'memberCart' => $memberCart];
            } else {
                return ['is_edit' => 0, 'memberCart' => $memberCart];
            }
        }
    }

    /**
     * 获取当前用户的购物车ID
     */
    private function getMemberCarId()
    {
        $cartId = null;
        $memberId = \YunShop::app()->getMemberId();
        $goods_id = request()->input('goods_id');

        if (!is_null($memberId) && !is_null($goods_id)) {
            $cartList = app('OrderManager')->make('MemberCart')->carts()->where('member_id', $memberId)
                ->orderBy('created_at', 'desc')
                ->get();

            if (!$cartList->isEmpty()) {
                collect($cartList)->map(function ($item, $key) use ($goods_id, &$cartId) {
                    if ($item->goods_id == $goods_id) {
                        $cartId = $item->id;
                    }
                });
            }
        }

        return $cartId;
    }
}
