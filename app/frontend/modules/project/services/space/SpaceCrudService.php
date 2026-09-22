<?php

namespace app\frontend\modules\project\services\space;

use app\common\exceptions\AppException;
use app\common\exceptions\ShopException;
use app\frontend\modules\cart\models\MemberCart;
use app\frontend\modules\project\infrastructure\BaseRepository;
use app\frontend\modules\project\models\Floors;
use app\frontend\modules\project\models\FloorSpace;
use app\frontend\modules\project\models\Project;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

/**
 * 空间CRUD服务
 * 负责：创建空间、编辑空间、删除空间、复制空间
 */
class SpaceCrudService
{
    /**
     * 创建空间
     */
    public function createSpace(int $project_id, int $floor_id, string $space_name): int
    {
        $project = Project::getProject($project_id);
        if (!$project) {
            throw new ShopException("项目不存在");
        }

        $floor = Floors::getFloor($floor_id, $project_id);
        if (!$floor) {
            throw new ShopException("楼层不存在");
        }
        if (!$space_name) {
            throw new ShopException("请输入空间名称");
        }

        $maxSort = FloorSpace::where('floor_id', $floor_id)
            ->where('project_id', $project_id)
            ->max('sort');

        $newSort = $maxSort ? $maxSort + 1 : 1;

        $FloorSpace = new FloorSpace();
        $space_data['project_id'] = $project->id;
        $space_data['floor_id'] = $floor->id;
        $space_data['name'] = $space_name;
        $space_data['sort'] = $newSort;
        $FloorSpace->fill($space_data);
        $validator = $FloorSpace->validator();
        if ($validator->fails()) {
            throw new ShopException($validator->messages());
        }
        if ($FloorSpace->save()) {
            $baseRepo = app(BaseRepository::class);
            $baseRepo->updateActivateCache(\YunShop::app()->getMemberId(), $project->id);
            return $FloorSpace->id;
        } else {
            throw new ShopException("创建空间失败");
        }
    }

    /**
     * 编辑空间
     */
    public function editSpace(int $space_id, string $space_name): bool
    {
        try {
            $space_model = FloorSpace::where('id', $space_id)->first();
            $space_model->name = $space_name;
            $space_model->save();

            $baseRepo = app(BaseRepository::class);
            $baseRepo->updateActivateCache(\YunShop::app()->getMemberId(), $space_model->project_id);

            return true;
        } catch (\Exception $e) {
            throw new ShopException("修改空间失败");
        }
    }

    /**
     * 删除空间
     */
    public function del_space(int $id): array
    {
        try {
            $space = FloorSpace::find($id);
            if (!$space) {
                throw new AppException('找不到空间');
            }
            $space->delete();
            app('OrderManager')->make('MemberCart')->where('space_id', $space->id)->forceDelete();
            $member_id = \YunShop::app()->getMemberId();
            $cacheKey = "member_cart_list_{$member_id}_{$id}";
            Cache::forget($cacheKey);

            $baseRepo = app(BaseRepository::class);
            $data = $baseRepo->updateActivateCache(\YunShop::app()->getMemberId(), $space->project_id);
            return ['project_statistics' => $data];
        } catch (\Exception $e) {
            throw new AppException(config("app.debug") ? $e->getMessage() : "异常失败");
        }
    }

    /**
     * 复制空间
     */
    public function copySpace(int $space_id): bool
    {
        $space_info = FloorSpace::where('id', $space_id)->first();
        $member_id = \YunShop::app()->getMemberId();

        if (!$space_info) {
            throw new AppException("空间数据不存在");
        }

        $is_order = Project::where('id', $space_info->project_id)->where('order_status', 1)->exists();
        if ($is_order) {
            throw new AppException("空间已下单，不能操作");
        }

        // 复制空间基本信息
        $new_space = $space_info->replicate();
        $new_space->name = $space_info->name . '_copy';
        $new_space->activate = 0;
        $new_space->save();

        // 复制 memberCart 数据
        $member_carts = MemberCart::where('space_id', $space_id)->orderBy('sort', 'asc')->orderBy('created_at', 'desc')->get();

        foreach ($member_carts as $cart) {
            $new_cart = $cart->replicate();
            $new_cart->space_id = $new_space->id;
            $new_cart->save();
        }
        Redis::del("user:{$member_id}:active_project_1");
        return true;
    }
}
