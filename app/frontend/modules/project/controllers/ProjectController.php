<?php

namespace app\frontend\modules\project\controllers;

use app\common\components\ApiController;
use app\common\exceptions\AppException;
use app\frontend\modules\cart\models\MemberCart;
use app\frontend\modules\project\models\Project;
use app\frontend\modules\project\services\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
class ProjectController extends ApiController
{

   // public $transactionActions =["savePlan"];
    /**
     *创建我的项目
     */

    private ProjectService $projectService;

    public function __construct(ProjectService $projectService)
    {

        $this->projectService = $projectService;
        parent::__construct();
    }


    public function createProject(Request $request)
    {

        $request_data = $request->input();
        $this->PreventDuplicateSubmission($request);
        $project_id = $this->projectService->createProject($request_data);
        return $this->successJson('ok', ['project_id' => $project_id]);
    }


    public function editProject(Request $request)
    {
        $request_data = $request->input();
        $this->PreventDuplicateSubmission($request);
        $this->projectService->editProject($request_data);
        return $this->successJson('ok');
    }

    public function getFloors(Request $request)
    {
        $this->validate([
            'project_id' => 'required|integer|min:0',
        ]);
        $project_id = $request->input("project_id");
        $list = $this->projectService->getFloors($project_id);
        return $this->successJson('ok', $list);
    }


    public function switchActiveProject(Request $request)
    {
        $this->validate([
            'project_id' => 'required|integer|min:0',
        ]);
        $project_id = $request->input("project_id");
        $space_id = $request->input("space_id", null);
        $floor_id = $request->input("floor_id", null);
      //  $this->PreventDuplicateSubmission($request);
        $this->projectService->switchActiveProject($project_id, $floor_id, $space_id);
        return $this->successJson('ok');
    }

    public function getActiveProject()
    {
        $list = $this->projectService->getActiveProject();
        return $this->successJson('ok', $list);
    }

    public function getMyProject()
    {

        $list = $this->projectService->getMyProject();
        return $this->successJson('ok', $list);
    }

    //退出方案
    public function logout(Request $request)
    {
        $this->validate([
            'project_id' => 'required|integer|min:0',
        ]);
        $project_id = $request->input("project_id");
        $this->projectService->logout($project_id);
        return $this->successJson('ok');
    }

    //创建楼层
    public function createFloor(Request $request)
    {
        $this->validate([
            'project_id' => 'required|integer|min:0',
            'floor_name' => 'required|string',
        ]);
        $project_id = $request->input("project_id");
        $floor_name = $request->input("floor_name");
        $this->PreventDuplicateSubmission($request);
        $result = $this->projectService->createFloor($project_id, $floor_name);
        return $this->successJson('ok', $result);
    }

    //整体改价
    public function updatePrice(Request $request)
    {
        $this->validate([
            'project_id' => 'required|integer|min:0',
            'ratio' => 'required|numeric|min:0',
        ]);

        $project_id = $request->input("project_id");
        $ratio = $request->input("ratio");
        $this->PreventDuplicateSubmission($request);
        $result = $this->projectService->updatePrice($project_id, $ratio);
        return $this->successJson('ok', $result);
    }

    //获取项目列表
    public function getList(Request $request)
    {


        $search = $request->input("search",[]);
        $data = $this->projectService->getList($search);
        return $this->successJson('ok', $data);
    }


    public function getProvinceCity(Request $request)
    {
//        $this->validate([
//            'id' => 'required|integer|min:0',
//        ]);
        $id = $request->input('id', 0);
        $data = $this->projectService->getProvinceCity($id);
        return $this->successJson('ok', $data);
    }

    public function delete(Request $request)
    {
        $this->validate([
            'project_id' => 'required|integer|min:0',
        ]);
        $id = $request->input('project_id', 0);
        $this->PreventDuplicateSubmission($request);
        $this->projectService->delete($id);
        return $this->successJson('ok');
    }


    public function updateStatus(Request $request)
    {
        $this->validate([
            'project_id' => 'required|integer|min:0',
            'status' => 'required|integer|min:0',
        ]);
        $id = $request->input('project_id');
        $this->PreventDuplicateSubmission($request);
        $status = $request->input('status', 0);
        $this->projectService->updateStatus($id, $status);
        return $this->successJson('ok');
    }

    //创建副本
    public function copyProject(Request $request)
    {
        $this->validate([
            'project_id' => 'required|integer|min:0',
        ]);
        $id = $request->input('project_id');
        $result = $this->projectService->copyProject($id);
        return $this->successJson('ok');
    }


    //获取项目详情

    public function detail(Request $request)
    {
        $this->validate([
            'project_id' => 'required|integer|min:0',
        ]);
        $id = $request->input('project_id');
        $data = $this->projectService->detail($id);
        return $this->successJson('ok',$data);
    }

    //删除平面图

    public function planDelete(Request $request)
    {
        $this->validate([
            'id' => 'required|integer|min:0',
        ]);
        $id = $request->input('id');
        $data = $this->projectService->planDelete($id);
        return $this->successJson('ok');
    }

    public function restore(Request $request)
    {
        $this->validate([
            'id' => 'required|integer|min:0',
        ]);
        $id = $request->input('id');
        $data = $this->projectService->restore($id);
        return $this->successJson('ok');
    }


    public function realDelete(Request $request)
    {
        $this->validate([
            'project_id' => 'required|integer|min:0',
        ]);
        $id = $request->input('project_id', 0);
        $this->PreventDuplicateSubmission($request);
        $this->projectService->realDelete($id);
        return $this->successJson('ok');
    }

    public function savePlan(Request $request)
    {
        $this->PreventDuplicateSubmission($request);
        $this->projectService->savePlan();
        return $this->successJson('ok');
    }


    public function getProjectData(Request $request)
    {
        $this->validate([
            'space_id' => 'required|integer|min:0',
        ]);
        $space_id = $request->input('space_id');
        $data = $this->projectService->getProjectData($space_id);
        return $this->successJson('ok',$data);
    }

    public function checkProjectName(Request $request)
    {
        $this->validate([
            'name' => 'required|string',
        ]);
        $name = $request->input('name');
        $project = Project::where('name',$name)->where('member_id',\YunShop::app()->getMemberId())->first();
        if($project){
            throw new AppException('项目名称已存在');
        }
        return $this->successJson('ok');
    }

    public function updateFloorCad(Request $request)
    {
    /*    $this->validate([
            'floor' => 'required|array',
            'floor.*.floor_id' => 'required|integer|exists:floors,id',
            'floor.*.imgBase64' => 'nullable|string',
            'floor.*.goods_total' => 'required|integer|min:1',
            'floor.*.space' => 'required|array|min:1',
            'floor.*.space.*.name' => 'required|string',
            'floor.*.space.*.goods' => 'required|array|min:1',
            'floor.*.space.*.goods.*.goods_id' => 'required|integer|exists:goods,id',
            'floor.*.space.*.goods.*.num' => 'required|integer|min:1',
            'floor.*.space.*.goods.*.option_id' => 'nullable|integer',
        ],$request, [
            'floor.*.space.required' => '每个楼层必须至少有一个空间',
            'floor.*.space.*.goods.required' => '每个空间必须至少包含一个商品',
    ]);*/

        $data = $request->input('floor');

        $this->projectService->updateFloorCad($data);
        return $this->successJson('ok');

    }


    public function updateSort(Request $request)
    {
        $items = $request->input('sorts', []);



        if (empty($items)) {
            return $this->errorJson('请发送数据');
        }
        $firstId = isset($items[0]['id']) ? $items[0]['id'] : null;

        $MemberCart = MemberCart::find($firstId);
        $memberId = \YunShop::app()->getMemberId();
        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                if (!isset($item['id'], $item['sort'])) {
                    continue;
                }
                DB::table('yz_member_cart')
                    ->where('id', $item['id'])
                    ->update([
                        'sort' => $item['sort'],
                        'updated_at' => time(), // 如果你想更新时间戳
                    ]);
            }
            $cacheKey = "member_cart_list_{$memberId}_{$MemberCart->space_id}";
            Cache::forget($cacheKey);
            DB::commit();
            return $this->successJson('修改成功');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorJson('修改失败');
        }
    }




}