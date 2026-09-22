<?php

namespace app\frontend\modules\project\services\project;

use app\common\exceptions\AppException;
use app\common\exceptions\ShopException;
use app\common\models\goods\GoodsOptionModel;
use app\common\models\MemberCart;
use app\frontend\modules\coupon\models\Goods;
use app\frontend\modules\project\infrastructure\BaseRepository;
use app\frontend\modules\project\models\Floors;
use app\frontend\modules\project\models\FloorSpace;
use app\frontend\modules\project\models\Project;
use Illuminate\Support\Facades\DB;

/**
 * 项目CRUD服务
 * 负责：创建项目、更新项目、复制项目、删除/恢复、状态变更
 */
class ProjectCrudService
{
    /**
     * 创建项目，包括楼层和空间
     */
    public function createProject(array $data): int
    {
        $requestData = $data['project'];
        if (!$requestData) {
            throw new ShopException("不合法的参数");
        }
        $project_data = Project::where('name', $requestData['name'])->where('member_id', \YunShop::app()->getMemberId())->first();
        if ($project_data) {
            throw new ShopException("项目名称已经存在");
        }

        if (!empty($requestData['floors'])) {
            foreach ($requestData['floors'] as &$floor) {
                if ($floor['imgBase64'] ?? null) {
                    $floor['thumb'] = BaseRepository::uploadOssThumb($floor['imgBase64']);
                }
            }
        }

        return DB::transaction(function () use ($requestData) {
            $projectData = [
                'member_id' => \YunShop::app()->getMemberId(),
                'uniacid' => \YunShop::app()->uniacid,
                'name' => $requestData['name'],
                'status' => $requestData['status'] ?: 0,
                'province_id' => $requestData['province_id'] ?: 0,
                'city_id' => $requestData['city_id'] ?: 0,
                'district_id' => $requestData['district_id'] ?: 0,
                'address_detail' => $requestData['address_detail'] ?: '',
                'contact_name' => $requestData['contact_name'] ?: '',
                'phone' => $requestData['phone'] ?: '',
            ];

            $project = Project::create($projectData);

            if (!$requestData['floors']) {
                $requestData['floors'] = [[
                    "name" => "默认楼层",
                    "spaces" => [["name" => "默认空间"]]
                ]];
            }

            if (!empty($requestData['floors'])) {
                foreach ($requestData['floors'] as $key => $floor) {
                    $floorData = [
                        'project_id' => $project->id,
                        'name' => $floor['name'],
                        'sort' => $key
                    ];

                    if ($floor['thumb'] && $floor['goods_total']) {
                        $floorData['is_cad'] = 1;
                        $floorData['thumb'] = $floor['thumb'];
                        $floorData['goods_total'] = $floor['goods_total'];
                    }
                    $floorModel = Floors::create($floorData);

                    if (!empty($floor['spaces'])) {
                        foreach ($floor['spaces'] as $k => $space) {
                            $spaceData = [
                                'project_id' => $project->id,
                                'floor_id' => $floorModel->id,
                                'name' => $space['name'],
                                'sort' => $k,
                            ];
                            $space_model = FloorSpace::create($spaceData);

                            if ($space['goods']) {
                                $goods_data = [];
                                foreach ($space['goods'] as $good) {
                                    $goods_data[] = [
                                        'project_id' => $project->id,
                                        'space_id' => $space_model->id,
                                        'floor_id' => $floorModel->id,
                                        'goods_id' => $good['goods_id'],
                                        'total' => $good['num'],
                                        'option_id' => $good['option_id'] ?: 0,
                                    ];
                                }
                                $this->addGoodsSpaceBatch($goods_data);
                            }
                        }
                    } else {
                        throw new ShopException("空间必须");
                    }
                }
            }

            return $project->id;
        });
    }

    /**
     * 简单更新项目基本信息
     */
    public function updateProject(array $data): bool
    {
        $requestData = $data['project'];
        $existing = Project::where('name', $requestData['name'])
            ->where('member_id', \YunShop::app()->getMemberId())
            ->where('id', '!=', $requestData['id'])
            ->exists();

        if ($existing) {
            throw new ShopException("项目名称已存在");
        }
        if (!$requestData) {
            throw new ShopException("不合法的参数");
        }
        $projectModel = Project::find($requestData['id']);
        if (!$projectModel) {
            throw new ShopException("没有找到项目");
        }

        $projectModel->name = $requestData['name'];
        $projectModel->status = $requestData['status'];
        $projectModel->province_id = $requestData['province_id'] ?: 0;
        $projectModel->city_id = $requestData['city_id'] ?: 0;
        $projectModel->district_id = $requestData['district_id'] ?: 0;
        $projectModel->address_detail = $requestData['address_detail'] ?: '';
        $projectModel->contact_name = $requestData['contact_name'] ?: '';
        $projectModel->phone = $requestData['phone'] ?: '';
        $projectModel->save();
        return true;
    }

    /**
     * 项目加入回收站或真删除
     */
    public function delete(int $id): bool
    {
        $is_recycle = request()->input('is_recycle');
        try {
            $Project = Project::withTrashed()->find($id);
            if ($Project->order_status == 1) {
                throw new ShopException("已下单项目不能删除");
            }
            if (!$Project) {
                throw new ShopException("找不到项目");
            }
            if ($Project->activate == 1) {
                throw new ShopException("项目正在配置中不能删除");
            }

            if ($is_recycle == 1) {
                $Project->forceDelete();
            } else {
                $Project->delete();
            }
            return true;
        } catch (\Exception $e) {
            throw new ShopException(config("app.debug") ? $e->getMessage() : "异常失败");
        }
    }

    /**
     * 更改项目状态
     */
    public function updateStatus(int $id, int $status): bool
    {
        $Project = Project::find($id);
        if (!$Project) {
            throw new ShopException("未找到项目");
        }
        try {
            $Project->status = $status;
            $Project->save();
            return true;
        } catch (\Exception $e) {
            throw new ShopException(config("app.debug") ? $e->getMessage() : "异常失败");
        }
    }

    /**
     * 真实删除
     */
    public function realDelete(int $id): bool
    {
        try {
            $project = Project::withTrashed()->find($id);
            if (!$project) {
                throw new AppException("未找到项目");
            }
            $project->forceDelete();
            return true;
        } catch (\Exception $e) {
            throw new AppException(config("app.debug") ? $e->getMessage() : "异常失败");
        }
    }

    /**
     * 还原回收站
     */
    public function restore(int $id): bool
    {
        try {
            $project = Project::withTrashed()->find($id);
            if (!$project) {
                throw new AppException("未找到项目");
            }
            $project->restore();
            return true;
        } catch (\Exception $e) {
            throw new AppException(config("app.debug") ? $e->getMessage() : "异常失败");
        }
    }

    /**
     * 创建项目副本
     */
    public function copyProject(int $id): bool
    {
        $originalProject = Project::with(['floors.spaces.spaceCart'])->find($id);
        if (!$originalProject) {
            throw new ShopException("项目不存在");
        }

        return DB::transaction(function () use ($originalProject) {
            $newProjectData = $originalProject->toArray();
            unset($newProjectData['id'], $newProjectData['created_at'], $newProjectData['updated_at'], $newProjectData['deleted_at']);
            $newProjectData['name'] = $this->generateUniqueProjectName($newProjectData['name']);
            $newProjectData['report_status'] = 0;
            $newProjectData['factory_status'] = 0;
            $newProjectData['bid_status'] = 0;
            $newProjectData['activate'] = 0;
            $newProjectData['order_id'] = 0;
            $newProjectData['price'] = 0;
            $newProjectData['order_status'] = 0;
            $newProject = Project::create($newProjectData);

            foreach ($originalProject->floors as $originalFloor) {
                $newFloorData = $originalFloor->toArray();
                unset($newFloorData['id'], $newFloorData['project_id']);
                $newFloorData['project_id'] = $newProject->id;
                $newFloor = Floors::create($newFloorData);

                foreach ($originalFloor->spaces as $originalSpace) {
                    $newSpaceData = $originalSpace->toArray();
                    unset($newSpaceData['id'], $newSpaceData['floor_id'], $newSpaceData['project_id']);
                    $newSpaceData['project_id'] = $newProject->id;
                    $newSpaceData['floor_id'] = $newFloor->id;
                    $newSpace = FloorSpace::create($newSpaceData);

                    foreach ($originalSpace->spaceCart as $originalGood) {
                        $newGoodsData = $originalGood->toArray();
                        unset($newGoodsData['id'], $newGoodsData['space_id'], $newGoodsData['floor_id'], $newGoodsData['project_id']);
                        $newGoodsData['project_id'] = $newProject->id;
                        $newGoodsData['floor_id'] = $newFloor->id;
                        $newGoodsData['space_id'] = $newSpace->id;
                        $this->addGoodsSpace($newGoodsData);
                    }
                }
            }
            return true;
        });
    }

    // ==================== 私有辅助方法 ====================

    private function generateUniqueProjectName($name)
    {
        if (preg_match('/_副本(\d+)?$/', $name, $matches)) {
            $baseName = $name;
        } else {
            $baseName = $name . '_副本';
        }
        $newName = $baseName;
        $count = 1;
        while (Project::where('name', $newName)->exists()) {
            $newName = $baseName . $count;
            $count++;
        }
        return $newName;
    }

    protected function addGoodsSpace(array $data)
    {
        $data['components'] = $data['components'] ?: json_encode([
            ["color_id" => 3, "component_id" => 4]
        ]);
        $data = array(
            'member_id' => \YunShop::app()->getMemberId(),
            'uniacid' => \YunShop::app()->uniacid,
            'project_id' => $data['project_id'],
            'space_id' => $data['space_id'],
            'floor_id' => $data['floor_id'],
            'goods_id' => $data['goods_id'],
            'type' => $data['type'] ?: 1,
            'total' => $data['total'],
            'option_id' => $data['option_id'] ?: 0,
            'components' => $data['components'],
            'components_hash' => md5($data['components'])
        );

        $cartModel = app('OrderManager')->make('MemberCart', $data);
        $hasGoodsModel = app('OrderManager')->make('MemberCart')->hasGoodsToMemberCart($data);
        if ($hasGoodsModel) {
            $num = intval($data['total']) ?: 1;
            $hasGoodsModel->total = max($hasGoodsModel->total + $num, 0);
            $hasGoodsModel->validate();
            if ($hasGoodsModel->update()) {
                return ['code' => 200];
            }
            throw new ShopException('数据更新失败，请重试！');
        }
        $cartModel->validate();
        $validator = $cartModel->validator($cartModel->getAttributes());
        if ($validator->fails()) {
            throw new ShopException("数据验证失败，加入空间失败！！！");
        } else {
            if ($cartModel->save()) {
                return ['code' => 200];
            } else {
                throw new ShopException("写入出错，加入空间失败！！！");
            }
        }
        throw new ShopException("接收数据出错，加入空间失败!");
    }

    protected function addGoodsSpaceBatch(array $list)
    {
        if (empty($list)) {
            return;
        }

        $optionIds = array_column($list, 'option_id');
        $optionIds = array_filter(array_unique($optionIds));

        $options = GoodsOptionModel::whereIn('option_id', $optionIds)
            ->select(['id', 'default_color', 'option_id'])
            ->get()
            ->groupBy('option_id')
            ->toArray();

        $insertList = [];

        foreach ($list as $data) {
            $optionId = $data['option_id'] ?: 0;
            $components = [];

            if ($optionId && isset($options[$optionId])) {
                foreach ($options[$optionId] as $option) {
                    $defaultColor = unserialize($option['default_color']);
                    if ($defaultColor === false) {
                        $defaultColor = [];
                    }
                    $components[] = [
                        "color_id" => $defaultColor['id'] ?? 3,
                        "component_id" => $option['id'] ?: 4,
                    ];
                }
            }

            if (empty($components)) {
                $components[] = ["color_id" => 3, "component_id" => 4];
            }
            $data['components'] = json_encode($components, JSON_UNESCAPED_UNICODE);
            $goods_info = Goods::where('id', $data['goods_id'])->first();
            if ($goods_info->type2 == 2) {
                $member_cart_info = MemberCart::withTrashed()->where('goods_id', $data['goods_id'])->where('member_id', \YunShop::app()->getMemberId())->first();
                if ($member_cart_info) {
                    $data['components'] = $member_cart_info->components;
                    $data['jsonData'] = $member_cart_info->jsonData;
                    $data['modelData'] = $member_cart_info->modelData;
                }
            }
            $new_data = array(
                'member_id' => \YunShop::app()->getMemberId(),
                'uniacid' => \YunShop::app()->uniacid,
                'project_id' => $data['project_id'],
                'space_id' => $data['space_id'],
                'floor_id' => $data['floor_id'],
                'goods_id' => $data['goods_id'],
                'type' => $goods_info->type2,
                'total' => $data['total'],
                'option_id' => $data['option_id'] ?: 0,
                'components' => $data['components'],
                'components_hash' => md5($data['components']),
                'jsonData' => $data['jsonData'] ?: null,
                'modelData' => $data['modelData'] ?: null,
            );
            $insertList[] = $new_data;
        }

        MemberCart::insert($insertList);
    }
}
