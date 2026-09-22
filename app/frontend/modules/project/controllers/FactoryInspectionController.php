<?php

namespace app\frontend\modules\project\controllers;

use app\common\components\ApiController;
use app\frontend\modules\project\services\FactoryInspectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class FactoryInspectionController extends ApiController
{

    /**
     *创建我的项目
     */

    private FactoryInspectionService $factoryInspectionService;

    public function __construct(FactoryInspectionService $factoryInspectionService)
    {
        $this->factoryInspectionService = $factoryInspectionService;
        parent::__construct();
    }



    //申请报备

    public function applyFor(Request $request)
    {
        $validat = [
            'project_id' => 'required|integer|min:0',
            'supplier_id' => 'required|integer|min:0',
            'name' => 'required|string',
            'applicant_name' => 'required|string',
            'applicant_phone' => 'required|string',
            'inspection_mode' => 'required|integer|min:0',
            'scheduled_inspection_date' => 'required|date',
            'scheduled_arrival_date' => 'required', //计划到达日期
            'projects_visit' => 'required',
            'travel_mode' => 'required|integer|min:0',

            'need_top_leader' => 'nullable|in:0,1',
            'play_video' => 'nullable|in:0,1',
            'is_led' => 'nullable|in:0,1',
            'need_hotel_booking' => 'nullable|in:0,1',

            'need_restaurant_booking' => 'nullable|in:0,1',
        ];

        if($request->input('inspection_mode') == 1){
            $validat['company_name'] = "required|string";
            $validat['contact_name'] = "required|string";
            $validat['contact_phone'] = "required|string";
            $validat['company_type'] = "required|array";
        }
        if($request->input('travel_mode') == 1){
            $validat['license_plate'] = "required|string";
        }
        if($request->input('travel_mode') == 2){
            $validat['wait_place'] = "required|string";
            $validat['wait_time'] = "required|string";
        }

        if($request->input('is_led') == 1){
            $validat['welcome_word'] = "required|string";

        }

        if($request->input('need_hotel_booking') == 1){
            $validat['payment_method'] = "required|integer";
            $validat['check_in_start_date'] = "required|date";
            $validat['check_in_end_date'] = "required|date";
            $validat['room_count_standard'] = "required|integer";
            $validat['room_count_single'] = "required|integer";

        }

        if($request->input('need_restaurant_booking') == 1){
            $validat['meal_start_date'] = "required|date";
            $validat['people_number'] = "required|integer";
            $validat['meal_type'] = "required|integer";
            $validat['price_range'] = "required|numeric";

        }

        $this->validate($validat);
        $validated = $request->validate($validat);

        $this->PreventDuplicateSubmission($request);
        $id = $this->factoryInspectionService->applyFor($validated);
        return $this->successJson('ok');
    }


    //获取项目列表
    public function getList(Request $request)
    {
        $search = $request->input("search",[]);
        $data = $this->factoryInspectionService->getList($search);
        return $this->successJson('ok', $data);
    }

    //考察一些数据获取
    public function getApplyData(Request $request)
    {
        $validat = [
            'project_id' => 'required|integer|min:0',
        ];
        $this->validate($validat);
        $id = $request->input("project_id");
        $data = $this->factoryInspectionService->getApplyData($id);
        return $this->successJson('ok',$data);

    }

    //获取报备推荐品牌数据
    public function getDetail(Request $request)
    {
        $this->validate(
            [
                'project_id' => 'required|integer|min:0',
            ]
        );
        $project_id = $request->input("project_id");
        $data = $this->factoryInspectionService->getDetail($project_id);
        return $this->successJson('ok',$data);
    }


    //删除
    public function delete(Request $request)
    {
        $this->validate(
            [
                'id' => 'required|integer|min:0',
            ]
        );
        $id = $request->input("id");
        $data = $this->factoryInspectionService->delete($id);
        return $this->successJson('ok');
    }


    public function cancelApply(Request $request)
    {
        $this->validate(
            [
                'id' => 'required|integer|min:0',
            ]
        );
        $id = $request->input("id");
        $this->factoryInspectionService->cancelApply($id);
        return $this->successJson('ok');
    }





}