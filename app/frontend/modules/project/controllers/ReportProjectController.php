<?php

namespace app\frontend\modules\project\controllers;

use app\common\components\ApiController;
use app\frontend\modules\project\services\ReportProjectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class ReportProjectController extends ApiController
{

    /**
     *创建我的项目
     */

    private ReportProjectService $reportProjectService;

    public function __construct(ReportProjectService $reportProjectService)
    {
        $this->reportProjectService = $reportProjectService;
        parent::__construct();
    }



    //申请报备

    public function applyFor(Request $request)
    {
        $validat = [
            'project_id' => 'required|integer|min:0',
            'supplier_id' => 'required|integer|min:0',
            'name' => 'required|string',
            'province_id' => 'required|integer|min:0',
            'city_id' => 'required|integer|min:0',
            'district_id' => 'required|integer|min:0',
            'address_detail' => 'required|string',
            'project_area'=>'required',
            'project_file'=>'required',
            'purchase_mode'=>'required|integer|min:0',
            'need_bid_document' => 'required|integer|min:0',
            'need_factory_inspection' => 'required|integer|min:0',
            'project_progress' => 'required',
            'budget'=>'required',
            'company_name' => 'required|string',
            'contact_name' => 'required|string',
            'contact_phone' => 'required|string',
            'company_address' => 'required|string',
            'company_type' => 'required',
        ];
        $this->validate($validat);
        $validated = $request->validate($validat);
        $this->PreventDuplicateSubmission($request);
        $id = $this->reportProjectService->applyFor($validated);
        return $this->successJson('ok', ['id' => $id]);
    }


    //获取项目列表
    public function getList(Request $request)
    {
        $search = $request->input("search",[]);
        $data = $this->reportProjectService->getList($search);
        return $this->successJson('ok', $data);
    }

    //报备详情
    public function detail(Request $request)
    {
        $validat = [
            'project_id' => 'required|integer|min:0',
        ];
        $this->validate($validat);
        $id = $request->input("project_id");
        $data = $this->reportProjectService->detail($id);
        return $this->successJson('ok',$data);

    }

    //获取报备推荐品牌数据
    public function getRecommendBrand(Request $request)
    {
        $this->validate(
            [
                'project_id' => 'required|integer|min:0',
            ]
        );
        $project_id = $request->input("project_id");
        $data = $this->reportProjectService->getRecommendBrand($project_id);
        return $this->successJson('ok',$data);
    }
    //搜索品牌
    public function searchBrand(Request $request)
    {
        $this->validate(
            [
                'name' => 'required|string',
            ]
        );
        $name = $request->input("name");
        $data = $this->reportProjectService->searchBrand($name);
        return $this->successJson('ok',$data);
    }

    //编辑报备

    public function edit(Request $request)
    {
        $validat = [
            'id' => 'required|integer|min:0',
            'project_id' => 'required|integer|min:0',
            'supplier_id' => 'required|integer|min:0',
            'name' => 'required|string',
            'province_id' => 'required|integer|min:0',
            'city_id' => 'required|integer|min:0',
            'district_id' => 'required|integer|min:0',
            'address_detail' => 'required|string',
            'project_area'=>'required',
            'project_file'=>'required',
            'purchase_mode.*'=>'required|integer|min:0',
            'need_bid_document' => 'required|integer|min:0',
            'need_factory_inspection' => 'required|integer|min:0',
            'project_progress' => 'required',
            'company_name' => 'required|string',
            'contact_name' => 'required|string',
            'contact_phone' => 'required|string',
            'company_address' => 'required|string',
            'company_type' => 'required',
        ];
        $this->validate($validat);
        $validated = $request->validate($validat);
        $this->PreventDuplicateSubmission($request);
        $this->reportProjectService->edit($validated);
        return $this->successJson('ok');
    }


    public function cancel(Request $request)
    {
        $this->validate(
            [
                'id' => 'required|integer|min:0',
            ]
        );
        $this->PreventDuplicateSubmission($request);
        $id = $request->input('id');
        $this->reportProjectService->cancel($id);
        return $this->successJson('ok');
    }


    public function getSearchData(Request $request)
    {
        $data = $this->reportProjectService->getSearchData();
        return $this->successJson('ok',$data);
    }



    public function upload(Request $request)
    {
        $data = $this->reportProjectService->upload();
        return $this->successJson('ok',$data);
    }










}