<?php

namespace app\backend\modules\cloud\controllers;

use app\common\components\BaseController;
use app\common\exceptions\ShopException;
use Illuminate\Http\Request;
use app\backend\modules\cloud\services\IssueTypeService;

class IssueTypeController extends BaseController
{
    private IssueTypeService $issueTypeService;


    public function __construct(IssueTypeService $issueTypeService)
    {
        $this->issueTypeService = $issueTypeService;
    }

    public function index()
    {

        return view('cloud.issueType.list')->render();
    }


    public function getData(Request $request)
    {
        $keyword = $request->keyword;
 
        $data = $this->issueTypeService->getList($keyword);
        return $this->successJson('获取成功', $data);
    }


    public function store(Request $request)
    {
        if ($request->ajax()) {
            $data = $request->input('data');

            if (!$data) {
                return $this->errorJson('参数错误');
            }

            if ($this->issueTypeService->save($data)) {
                return $this->successJson('添加成功');
            }

            return $this->errorJson('添加失败');
        }
        return view('cloud.issueType.info')->render();
    }

    public function update(Request $request)
    {
        $id = $request->input('id');
        if ($request->ajax()) {
        
            $data = $request->input('data');

      
            if (!$id || !$data) {
                return $this->errorJson('参数错误');
            }


            if ($this->issueTypeService->save($data, $id)) {
                return $this->successJson('更新成功');
            }

            return $this->errorJson('更新失败');
        }
        $data = $this->issueTypeService->getOne($id);
        return view('cloud.issueType.info', ['data' => $data])->render();
    }

    public function enable(Request $request)
    {
        $id = $request->input('id');
        if (!$id) {
            return $this->errorJson('参数错误');
        }

        if ($this->issueTypeService->enable($id)) {
            return $this->successJson('启用成功');
        }

        return $this->errorJson('启用失败');
    }

    public function delete(Request $request)
    {
        $id = $request->input('id');
        if (!$id) {
            return $this->errorJson('参数错误');
        }

        if ($this->issueTypeService->delete($id)) {
            return $this->successJson('删除成功');
        }

        return $this->errorJson('删除失败');
    }

    // //详情
    // public function detail(Request $request)
    // {
    //     $id = $request->input('id');
    //     if (!$id) {
    //         return $this->errorJson('参数错误');
    //     }
    //     $data = $this->issueTypeService->getOne($id);
    //     return $this->successJson('获取成功', $data);
    // }
}
