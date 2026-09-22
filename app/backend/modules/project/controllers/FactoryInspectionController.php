<?php

namespace app\backend\modules\project\controllers;

use app\common\models\Address;
use app\common\models\project\ProjectProgress;
use app\common\components\BaseController;

use app\backend\modules\project\services\FactoryInspectService;
use app\common\models\project\PurchasingModes;
use app\common\models\project\ProjectReport;
class FactoryInspectionController extends BaseController
{
    protected $factoryInspectService;

    public function __construct(FactoryInspectService $factoryInspectService)
    {
        $this->factoryInspectService = $factoryInspectService;
        parent::__construct();
    }

    public function index()
    {

        if(request()->ajax()){
            if(request()->input('mtype') == "city"){
                $province_id = request()->input('province_id');
                $cityData = $this->factoryInspectService->getProvinceData('city_id',$province_id);
                return $this->successJson('ok',$cityData);
            }
            $list = $this->factoryInspectService->index();
            $list = $list->toArray();
            return $this->successJson('ok',$list);
        }
        $purchasing_models = $this->factoryInspectService->getPurchasingModel();
        $company_type = ProjectProgress::getCompanyType(2);
        //获取省级
        $province = $this->factoryInspectService->getProvinceData('province_id');
        return view('project.factory.list',['purchasing_models'=>$purchasing_models,'company_data'=>collect($company_type),'province'=>$province])->render();
    }


    public function detail()
    {
        $id = request()->input('id');
        if(request()->ajax()){

            $data = $this->factoryInspectService->getDetail($id);
            return $this->successJson('ok',$data);
        }


        return view('project.factory.detail',['id'=>$id])->render();
    }


    public function apply()
    {
        if(request()->ajax()){
            $id = request()->input('id');
            $status = request()->input('status');
            $this->factoryInspectService->apply($id,$status);
            return $this->successJson('操作成功');
        }
    }




}