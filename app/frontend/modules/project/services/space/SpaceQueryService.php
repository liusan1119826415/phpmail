<?php

namespace app\frontend\modules\project\services\space;

use app\frontend\modules\cart\services\GroupManager;
use app\frontend\modules\project\infrastructure\BaseRepository;
use Illuminate\Support\Facades\Cache;

/**
 * 空间查询服务
 * 负责：获取空间商品列表（V1/V2）
 */
class SpaceQueryService
{
    /**
     * 获取空间商品列表 V1
     */
    public function getProductList(int $space_id): array
    {
        $member_id = \YunShop::app()->getMemberId();

        $cartList = app('CartContainer')->make('MemberCart')->carts()
            ->where(app('CartContainer')->make('MemberCart')->getTable() . '.member_id', $member_id)
            ->where(app('CartContainer')->make('MemberCart')->getTable() . '.space_id', $space_id)
            ->with(["hasManyAddress" => function ($query) use ($member_id) {
                return $query->where("uid", $member_id)->where("isdefault", 1);
            }])
            ->with(["hasManyMemberAddress" => function ($query) use ($member_id) {
                return $query->where("uid", $member_id)->where("isdefault", 1);
            }])
            ->orderBy(app('CartContainer')->make('MemberCart')->getTable() . '.created_at', 'desc')
            ->get();

        $manager = new GroupManager();
        $manager->init($cartList);
        $cartLists = $manager->cartList();
        return $cartLists;
    }

    /**
     * 获取空间商品列表 V2（含缓存）
     */
    public function getProductListV2(int $space_id): array
    {
        $member_id = \YunShop::app()->getMemberId();
        $cacheKey = "member_cart_list_{$member_id}_{$space_id}";
        $data = Cache::get($cacheKey);

        // if ($data) {
        //     return $data;
        // }

        $baseRepo = app(BaseRepository::class);
        $data = $baseRepo->processProduct($member_id, $space_id);
        Cache::put($cacheKey, $data, 1440 + rand(10, 99));
        return $data;
    }
}
