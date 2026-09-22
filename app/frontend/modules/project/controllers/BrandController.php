<?php


namespace app\frontend\modules\project\controllers;

use app\common\components\ApiController;
use app\common\models\kefu\ServiceUser;
use app\frontend\modules\project\services\BrandService;
use Illuminate\Http\Request;

class BrandController extends ApiController
{
    private BrandService $brandService;

    protected $publicAction = ["getBrandOther","conditions","getServiceUrl",'getBrandGoods'];
    protected $ignoreAction = ["getBrandOther","conditions","getServiceUrl",'getBrandGoods'];

    public function __construct(BrandService $brandService)
    {
        $this->brandService = $brandService;
        parent::__construct();
    }

    public function conditions()
    {
        $list = $this->brandService->conditions();
        return $this->successJson('ok',$list);
    }

    public function search(Request $request)
    {

        $search = $request->input('search');
        $list = $this->brandService->search($search);
        return $this->successJson('ok',$list);
    }

    public function detail(Request $request)
    {
        $this->validate([
            'supplier_id' => 'required|integer|min:0',
        ]);
        $supplier_id = $request->input('supplier_id');
        $list = $this->brandService->detail($supplier_id);
        return $this->successJson('ok',$list);
    }

    public function getBrandGoods(Request $request)
    {
        $this->validate([
            'supplier_id' => 'required|integer|min:0',
        ]);
        $supplier_id = $request->input('supplier_id');
        $list = $this->brandService->getBrandGoods($supplier_id);
        return $this->successJson('ok',$list);
    }

    public function getBrandOther(Request $request)
    {
        $this->validate([
            'supplier_id' => 'required|integer|min:0',
            'other_type' => 'required|integer|min:0',
        ]);
        $supplier_id = $request->input('supplier_id');
        $other_type = $request->input('other_type');
        $list = $this->brandService->getBrandOther($supplier_id,$other_type);
        return $this->successJson('ok',$list);
    }


    public function follow(Request $request)
    {
        $this->validate([
            'supplier_id' => 'required|integer|min:0',
            'follow_type' => 'required|integer|min:0',
        ]);
        $supplier_id = $request->input('supplier_id');
        $follow_type = $request->input('follow_type');
        $this->brandService->follow($supplier_id,$follow_type);
        return $this->successJson('ok');
    }

    public function getServiceUrl()
    {
        $url = ServiceUser::getDistributeService(0);
        return $this->successJson('ok',['url'=>$url]);

    }
}