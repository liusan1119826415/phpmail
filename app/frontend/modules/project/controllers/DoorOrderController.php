<?php

namespace app\frontend\modules\project\controllers;

use app\common\components\ApiController;
use app\frontend\modules\project\services\DoorOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class DoorOrderController extends ApiController
{


    public $transactionActions =["reqDoor"];

    /**
     *创建我的项目
     */

    private DoorOrderService $doorOrderService;

    public function __construct(DoorOrderService $doorOrderService)
    {
        $this->doorOrderService = $doorOrderService;
        parent::__construct();
    }


    public function acceptanceCheck(Request $request)
    {

        $this->validate([
            'id' => 'required|integer|min:0',
        ]);
        $id = $request->input('id');
        $this->doorOrderService->acceptanceCheck($id);
        return $this->successJson('ok');

    }




    public function reqDoor(Request $request)
    {

        $validat = [
            'project_id' => 'required|integer|min:0',
            'province_id' => 'required|integer|min:0',
            'city_id' => 'required|integer|min:0',
            'district_id' => 'required|integer|min:0',
            'address_detail' => 'required|string',
            'project_area'=>'required',
            'number'=>'required',
            'service_id'=>'required',
            'service_type.*' => 'integer|min:0',
            'door_time' => 'required',
            'contact_name' => 'required|string',
            'contact_phone' => 'required|string',
            'service_day' => 'required',
        ];
        $this->validate($validat, $request, [
            'project_id.required' => '请选择项目',
            'province_id.required' => '请选择省',
            'city_id.required' => '请选择市',
            'district_id.required' => '请选择区',
            'address_detail.required' => '请输入详细地址',
            'project_area.required' => '请输入项目面积',
            'service_id.required' => '请选择服务类目',
            'service_type.required' => '请选择服务类型',
            'door_time.required' => '请选择上门时间',
            'contact_name.required' => '请输入联系人',
            'contact_phone.required' => '请输入联系人手机',
            'service_day.required' => '请输入上门服务天数',
        ]);
        $validated = $request->validate($validat);
        $this->PreventDuplicateSubmission($request);
        $data = $this->doorOrderService->reqDoor($validated);
        return $this->successJson('ok',$data);
    }

    public function getProjectService(Request $request)
    {
        $this->validate([
            'project_id' => 'required|integer|min:0',
        ]);
        $project_id = $request->input('project_id');
        $data = $this->doorOrderService->getProjectService($project_id);
        return $this->successJson('ok',$data);

    }

    public function getProjectList(Request $request)
    {
        $data = $this->doorOrderService->getProjectList();
        return $this->successJson('ok',$data);
    }

    public function getList(Request $request)
    {
        $search = $request->input('search',[]);
        $data = $this->doorOrderService->getList($search);
        return $this->successJson('ok',$data);
    }

    public function getDetail(Request $request)
    {
        $this->validate([
            'id' => 'required|integer|min:0',
        ]);
        $id = $request->input('id');
        $data = $this->doorOrderService->getDetail($id);
        return $this->successJson('ok',$data);
    }

    public function addAmount(Request $request)
    {
        $this->validate([
            'id' => 'required|integer|min:0',
            'extra_enable' => 'required|integer|min:0',
            'extra_amount' => 'required|numeric|min:1',
        ]);
        $id = $request->input('id');
        $extra_amount = $request->input('extra_amount');
        $extra_enable = $request->input('extra_enable');
        $this->doorOrderService->addAmount($id,$extra_amount,$extra_enable);
        return $this->successJson('ok');
    }


    public function getServiceFee(Request $request)
    {
        $this->validate([
            'supplier_id' => 'required|integer|min:0',
        ]);
        $supplier_id = $request->input('supplier_id');
        $data = $this->doorOrderService->getServiceFee($supplier_id);
        return $this->successJson('ok',$data);
    }


    public function getDoorFee(Request $request)
    {
        $this->validate([
            'order_id' => 'required|integer|min:0',
        ]);
        $order_id = $request->input('order_id');
        $data = $this->doorOrderService->getDoorFee($order_id);
        return $this->successJson('ok',$data);
    }


    public function getAfterSales(Request $request)
    {
        $search = $request->input("search",[]);
        $data = $this->doorOrderService->getAfterSales($search);
        return $this->successJson('ok',$data);

    }










}