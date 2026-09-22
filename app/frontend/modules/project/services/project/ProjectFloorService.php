<?php

namespace app\frontend\modules\project\services\project;

use app\common\exceptions\AppException;
use app\common\exceptions\ShopException;
use app\common\models\MemberCart;
use app\common\models\project\PlanImg;
use app\frontend\models\OrderGoods;
use app\frontend\modules\project\infrastructure\BaseRepository;
use app\frontend\modules\project\models\Floors;
use app\frontend\modules\project\models\FloorSpace;
use app\frontend\modules\project\models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * 项目楼层/空间管理服务
 * 负责：编辑项目(含楼层空间)、激活/退出、平面图、楼层排序
 */
class ProjectFloorService
{
    /**
     * 编辑项目（含楼层空间的增删改）
     */
    public function editProject(array $data): bool
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

        return DB::transaction(function () use ($requestData) {
            $projectModel = Project::find($requestData['id']);
            if (!$projectModel) {
                throw new ShopException("没有找到项目");
            }

            $currentActiveFloorId = Floors::where('project_id', $projectModel->id)->where('activate', 1)->value('id');
            $currentActiveSpaceId = FloorSpace::where('project_id', $projectModel->id)->where('activate', 1)->value('id');

            $projectModel->name = $requestData['name'];
            $projectModel->status = $requestData['status'];
            $projectModel->province_id = $requestData['province_id'] ?: 0;
            $projectModel->city_id = $requestData['city_id'] ?: 0;
            $projectModel->district_id = $requestData['district_id'] ?: 0;
            $projectModel->address_detail = $requestData['address_detail'] ?: '';
            $projectModel->contact_name = $requestData['contact_name'] ?: '';
            $projectModel->phone = $requestData['phone'] ?: '';
            $projectModel->save();

            $floorsIds = [];
            $needToSetNewActiveFloor = false;
            $needToSetNewActiveSpace = false;

            if (!empty($requestData['floors'])) {
                $floorIdsFromRequest = array_column($requestData['floors'], 'id');
                if ($currentActiveFloorId && !in_array($currentActiveFloorId, $floorIdsFromRequest)) {
                    $needToSetNewActiveFloor = true;
                }
                if (!$currentActiveFloorId) {
                    $needToSetNewActiveFloor = true;
                }

                $firstFloorId = null;
                $firstFloor = null;

                foreach ($requestData['floors'] as $key => $floorData) {
                    $floor = $this->updateOrCreateFloor($projectModel, $floorData, $key);
                    $floorsIds[] = $floor->id;

                    if ($firstFloorId === null) {
                        $firstFloorId = $floor->id;
                        $firstFloor = $floor;
                    }

                    $spaceIdsFromRequest = [];
                    if (!empty($floorData['spaces'])) {
                        $spaceIdsFromRequest = array_column($floorData['spaces'], 'id');
                        if ($currentActiveSpaceId) {
                            $currentActiveSpace = FloorSpace::find($currentActiveSpaceId);
                            if ($currentActiveSpace && $currentActiveSpace->floor_id == $floor->id) {
                                if (!in_array($currentActiveSpaceId, $spaceIdsFromRequest)) {
                                    if ($floor->id == $currentActiveFloorId) {
                                        $needToSetNewActiveSpace = true;
                                    }
                                }
                            }
                        }
                    }

                    $spacesIds = $this->updateOrCreateSpaces($floor, $floorData['spaces']);

                    if ($floor->id == $currentActiveFloorId) {
                        $activeSpaceExists = FloorSpace::where('id', $currentActiveSpaceId)->where('floor_id', $floor->id)->exists();
                        if (!$activeSpaceExists || $needToSetNewActiveSpace) {
                            $firstSpace = FloorSpace::where('floor_id', $floor->id)->orderBy('id')->first();
                            if ($firstSpace) {
                                FloorSpace::where('floor_id', $floor->id)->update(['activate' => 0]);
                                $firstSpace->activate = 1;
                                $firstSpace->save();
                                $needToSetNewActiveSpace = false;
                            }
                        }
                    }

                    FloorSpace::where('floor_id', $floor->id)->whereNotIn('id', $spacesIds)->delete();
                }

                if ($needToSetNewActiveFloor && $firstFloorId) {
                    Floors::where('project_id', $projectModel->id)->update(['activate' => 0]);
                    $firstFloor->activate = 1;
                    $firstFloor->save();

                    $firstSpace = FloorSpace::where('floor_id', $firstFloorId)->orderBy('id')->first();
                    if ($firstSpace) {
                        FloorSpace::where('project_id', $projectModel->id)->update(['activate' => 0]);
                        $firstSpace->activate = 1;
                        $firstSpace->save();
                    }
                } elseif ($currentActiveFloorId && !$needToSetNewActiveFloor) {
                    $activeSpaceExists = FloorSpace::where('floor_id', $currentActiveFloorId)->where('activate', 1)->exists();
                    if (!$activeSpaceExists) {
                        $firstSpace = FloorSpace::where('floor_id', $currentActiveFloorId)->orderBy('id')->first();
                        if ($firstSpace) {
                            FloorSpace::where('floor_id', $currentActiveFloorId)->update(['activate' => 0]);
                            $firstSpace->activate = 1;
                            $firstSpace->save();
                        }
                    }
                }

                $this->deleteUnusedFloors($projectModel, $floorsIds);
            } else {
                Floors::where('project_id', $projectModel->id)->delete();
            }

            $baseRepo = app(BaseRepository::class);
            $baseRepo->updateActivateCache(\YunShop::app()->getMemberId(), $projectModel->id);
            return true;
        });
    }

    /**
     * 空间排序（拖拽楼层空间）
     */
    public function spaceSort(array $data): bool
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

        return DB::transaction(function () use ($requestData) {
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

            $floorsIds = [];
            if (!empty($requestData['floors'])) {
                $allSpaceIds = [];
                foreach ($requestData['floors'] as $key => $floorData) {
                    $floor = $this->updateOrCreateFloor($projectModel, $floorData, $key);
                    $floorsIds[] = $floor->id;
                    $spaceIds = $this->updateSpacesFloorId($floor, $floorData['spaces']);
                    $allSpaceIds = array_merge($allSpaceIds, $spaceIds);
                    $this->updateCartFloorId($floor->id, $spaceIds);
                }
            }

            $baseRepo = app(BaseRepository::class);
            $baseRepo->updateActivateCache(\YunShop::app()->getMemberId(), $projectModel->id);
            return true;
        });
    }

    /**
     * 创建楼层
     */
    public function createFloor(int $project_id, string $floor_name): array
    {
        $project = Project::getProject($project_id);
        if (!$project) {
            throw new ShopException("未找到该项目");
        }
        if (!$floor_name) {
            throw new ShopException("请输入楼层名称");
        }
        $Floors = new Floors();
        $space_data['project_id'] = $project->id;
        $space_data['name'] = $floor_name;
        $Floors->fill($space_data);
        $validator = $Floors->validator();
        if ($validator->fails()) {
            throw new ShopException($validator->messages());
        }
        if ($Floors->save()) {
            $space_data['project_id'] = $project->id;
            $space_data['floor_id'] = $Floors->id;
            $space_data['name'] = "未命名空间";
            $space = FloorSpace::create($space_data);
            $baseRepo = app(BaseRepository::class);
            $baseRepo->updateActivateCache(\YunShop::app()->getMemberId(), $project->id);
            return ['floor_id' => $Floors->id, 'space_id' => $space->id];
        } else {
            throw new ShopException("创建楼层失败");
        }
    }

    /**
     * 激活配置项目
     */
    public function switchActiveProject(int $project_id, int $floor_id = null, int $space_id = null): bool
    {
        $member_id = \YunShop::app()->getMemberId();
        DB::beginTransaction();
        try {
            $project = Project::getProject($project_id);
            if (!$project) {
                DB::rollBack();
                throw new ShopException("未找到该项目");
            }
            $project->activate = 1;
            $project->save();

            if (empty($floor_id) && empty($space_id)) {
                $firstFloor = Floors::getFirstFloor($project_id);
                if (!$firstFloor) { DB::rollBack(); throw new ShopException('该项目没有楼层'); }
                $firstSpace = FloorSpace::getFirstSpace($firstFloor->id);
                if (!$firstSpace) { DB::rollBack(); throw new ShopException('该楼层没有空间'); }
                $floor_id = $firstFloor->id;
                $space_id = $firstSpace->id;
            } elseif (empty($space_id) && !empty($floor_id)) {
                $firstSpace = FloorSpace::getFirstSpace($floor_id);
                if (!$firstSpace) { DB::rollBack(); throw new ShopException('该楼层没有空间'); }
                $space_id = $firstSpace->id;
            } elseif (!empty($space_id) && !empty($floor_id)) {
                $floorSpace = FloorSpace::where('project_id', $project_id)->where('floor_id', $floor_id)->where('id', $space_id)->first();
                if (!$floorSpace) { DB::rollBack(); throw new ShopException('该楼层没有此空间'); }
            }

            Floors::updateActivateFloor($project_id, $floor_id);
            FloorSpace::updateActivateSpace($floor_id, $space_id);
            Project::updateOtherActivate($project_id);
            Floors::updateOtherActivate($project_id, $floor_id);
            FloorSpace::updateOtherActivate($project_id, $space_id);

            $baseRepo = app(BaseRepository::class);
            $baseRepo->updateActivateCache($member_id, $project_id);
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new ShopException(config("app.debug") ? $e->getMessage() : "异常失败");
        }
    }

    /**
     * 获取当前激活项目
     */
    public function getActiveProject(): array
    {
        $member_id = \YunShop::app()->getMemberId();
        $cachedProjectData = Redis::get("user:{$member_id}:active_project_1");
        if ($cachedProjectData) {
            return json_decode($cachedProjectData, true);
        }
        $projectId = Project::where('member_id', $member_id)->where('activate', 1)->value('id');
        if ($projectId) {
            $baseRepo = app(BaseRepository::class);
            return $baseRepo->updateActivateCache($member_id, $projectId) ?: [];
        }
        return [];
    }

    /**
     * 退出方案
     */
    public function logout(int $project_id): bool
    {
        try {
            $member_id = \YunShop::app()->getMemberId();
            Project::where('id', $project_id)->where('activate', 1)->update(['activate' => 0]);
            Floors::where('project_id', $project_id)->where('activate', 1)->update(['activate' => 0]);
            FloorSpace::where('project_id', $project_id)->where('activate', 1)->update(['activate' => 0]);
            Redis::del("user:{$member_id}:active_project_1");
            return true;
        } catch (\Exception $e) {
            throw new ShopException("退出失败");
        }
    }

    /**
     * 更新平面图CAD
     */
    public function updateFloorCad(array $requestData): bool
    {
        if (!$requestData) {
            throw new ShopException("不合法的参数");
        }
        $floor = Floors::find($requestData['floor_id']);
        if (!$floor) {
            throw new ShopException("楼层不存在");
        }
        if ($requestData['imgBase64'] && $requestData['goods_total']) {
            $thumb = BaseRepository::uploadOssThumb($requestData['imgBase64']);
            $floor->thumb = $thumb;
            $floor->goods_total = $requestData['goods_total'];
            $floor->save();
        }

        return DB::transaction(function () use ($requestData, $floor) {
            if (!empty($requestData['spaces'])) {
                $this->deleteSpace($floor->id);
                foreach ($requestData['spaces'] as $k => $space) {
                    $spaceData = [
                        'project_id' => $floor->project_id,
                        'floor_id' => $floor->id,
                        'name' => $space['name'],
                        'sort' => $k,
                    ];
                    $space_model = FloorSpace::create($spaceData);
                    if ($space['goods']) {
                        $goods_data = [];
                        foreach ($space['goods'] as $good) {
                            $goods_data[] = [
                                'project_id' => $floor->project_id,
                                'space_id' => $space_model->id,
                                'floor_id' => $floor->id,
                                'goods_id' => $good['goods_id'],
                                'total' => $good['num'],
                                'option_id' => $good['option_id'] ?: 0,
                            ];
                        }
                        // 使用 CrudService 的批量添加
                        app(\app\frontend\modules\project\services\project\ProjectCrudService::class)->addGoodsSpaceBatch($goods_data);
                    }
                }
                $project_activate = Project::where('id', $floor->project_id)->where('activate', 1)->first();
                if ($project_activate) {
                    $this->logout($floor->project_id);
                }
            } else {
                throw new ShopException("空间必须");
            }
            return true;
        });
    }

    /**
     * 保存平面图
     */
    public function savePlan(): bool
    {
        $data = request()->input('plan');
        $project = Project::find($data['project_id']);
        if (!$project) {
            throw new ShopException('项目不存在');
        }
        try {
            if ($data['floors']) {
                $floorIds = collect($data['floors'])->pluck('floor_id');
                if ($floorIds->count() !== $floorIds->unique()->count()) {
                    throw new AppException("不能选择有重复的楼层");
                }
                foreach ($data['floors'] as $item) {
                    if (!$item['floor_id']) {
                        throw new AppException("请选择楼层");
                    }
                    $planImgModel = PlanImg::where('floor_id', $item['floor_id'])->where('project_id', $project->id)->first();
                    if ($planImgModel) {
                        $planImgModel->thumb = $item['output_img'];
                        $planImgModel->dxf_url = $item['dxf_file'];
                        $planImgModel->goods_total = $item['goods_total'];
                        $planImgModel->original_file = $item['original_file'];
                        $planImgModel->save();
                    } else {
                        PlanImg::create([
                            'project_id' => $project->id,
                            'floor_id' => $item['floor_id'],
                            'thumb' => $item['output_img'],
                            'dxf_url' => $item['dxf_file'],
                            'original_file' => $item['original_file'],
                            'goods_total' => $item['goods_total']
                        ]);
                    }
                }
                return true;
            } else {
                throw new AppException("请上传cad数据");
            }
        } catch (\Exception $e) {
            throw new AppException(config("app.debug") ? $e->getMessage() : "异常失败");
        }
    }

    /**
     * 平面图删除
     */
    public function planDelete(int $id): bool
    {
        try {
            $PlanImg = PlanImg::find($id);
            if (!$PlanImg) {
                throw new AppException("平面数据不存在");
            }
            $PlanImg->delete();
            return true;
        } catch (\Exception $e) {
            throw new AppException(config("app.debug") ? $e->getMessage() : "异常失败");
        }
    }

    // ==================== 私有辅助方法 ====================

    private function updateOrCreateFloor(Project $projectModel, array $floorData, int $key): Floors
    {
        $floor = Floors::find($floorData['id']);
        if ($floor) {
            $floor->name = $floorData['name'];
            $floor->sort = $key;
            $floor->save();
        } else {
            if (empty($floorData['name'])) {
                throw new ShopException("请填入楼层名称");
            }
            $floor = Floors::create([
                'project_id' => $projectModel->id,
                'name' => $floorData['name'],
                'sort' => $key
            ]);
        }
        return $floor;
    }

    private function updateOrCreateSpaces(Floors $floor, array $spacesData): array
    {
        $spacesIds = [];
        $space_all = FloorSpace::where('floor_id', $floor->id)->pluck("id")->all();
        foreach ($spacesData as $k => $spaceData) {
            $space = FloorSpace::find($spaceData['id']);
            if ($space) {
                if (!in_array($spaceData['id'], $space_all)) {
                    $space->floor_id = $floor->id;
                }
                $space->name = $spaceData['name'];
                $space->sort = $k;
                $space->save();
            } else {
                if (empty($spaceData['name'])) {
                    throw new ShopException("请填入空间名称");
                }
                $space = FloorSpace::create([
                    'project_id' => $floor->project_id,
                    'floor_id' => $floor->id,
                    'name' => $spaceData['name'],
                    'sort' => $k,
                ]);
            }
            $spacesIds[] = $space->id;
        }
        return $spacesIds;
    }

    private function deleteUnusedFloors(Project $projectModel, array $floorsIds)
    {
        Floors::where('project_id', $projectModel->id)
            ->whereNotIn('id', $floorsIds)
            ->get()
            ->each(function ($floor) {
                $floor->spaces()->delete();
                $floor->delete();
            });
    }

    private function updateSpacesFloorId($floor, $spacesData): array
    {
        $spaceIds = [];
        foreach ($spacesData as $spaceIndex => $spaceData) {
            if (!empty($spaceData['id'])) {
                FloorSpace::where('id', $spaceData['id'])->update([
                    'floor_id' => $floor->id,
                    'name' => $spaceData['name'] ?? '',
                    'activate' => $spaceData['activate'] ?? 0,
                    'sort' => $spaceIndex,
                    'updated_at' => time()
                ]);
                $spaceIds[] = $spaceData['id'];
            }
        }
        return $spaceIds;
    }

    private function updateCartFloorId(int $floorId, array $spaceIds)
    {
        if (!empty($spaceIds)) {
            MemberCart::whereIn('space_id', $spaceIds)->update(['floor_id' => $floorId]);
            OrderGoods::whereIn('space_id', $spaceIds)->update(['floor_id' => $floorId]);
        }
    }

    private function deleteSpace($floor_id)
    {
        FloorSpace::where('floor_id', $floor_id)->delete();
        MemberCart::where('floor_id', $floor_id)->delete();
    }
}
