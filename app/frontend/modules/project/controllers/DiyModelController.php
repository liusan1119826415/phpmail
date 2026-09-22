<?php

namespace app\frontend\modules\project\controllers;

use app\common\components\ApiController;
use app\common\models\Goods;
use app\frontend\modules\project\services\BidOrderService;
use app\frontend\modules\project\services\DiyModelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use app\common\models\project\CloudDesign;
class DiyModelController extends ApiController
{



    protected $publicAction = ["getPublicDesignDetail","getPublicGoodsStatus"];
    protected $ignoreAction = ["getPublicDesignDetail", "getPublicGoodsStatus"];

    /**
     *创建我的项目
     */

    private DiyModelService $diyModelService;

    public function __construct(DiyModelService $diyModelService)
    {
        $this->diyModelService = $diyModelService;
        parent::__construct();
    }


    public function getPublicDesignDetail(Request $request)
    {
        $design_id = $request->input("id");
        
        $data = $this->diyModelService->getDesignDetail($design_id);
      
        return $this->successJson('ok',$data);
    }

    public function getPublicGoodsStatus(Request $request)
    {
        
        //验证参数
        $this->validate([
            'goodsIds' => 'required|array',
        ], $request, [
            'goodsIds.required' => '请选择商品',
            'goodsIds.array' => '商品参数必须是数组',
        ]);
        $goodsIds = $request->input('goodsIds', []);

        $data = $this->diyModelService->getGoodsStatus($goodsIds);

        return $this->successJson('ok', $data);
    }





    public function seriesGoods(Request $request)
    {
        $goods_id = $request->input("goods_id",0);
        $data = $this->diyModelService->seriesGoods($goods_id);
        
        return $this->successJson('ok',$data);

    }

    public function relatedProducts(Request $request)
    {
        $goods_id = $request->input("goods_id",0);
        $data = $this->diyModelService->relatedProducts($goods_id);
        return $this->successJson('ok',$data);
    }

    public function designList(Request $request)
    {

        $search = $request->input("search",[]);
        $data  =  $this->diyModelService->designList($search);
        return $this->successJson('ok',$data);
    }

    public function duplicateDesign(Request $request)
    {
        $design_id = $request->input("design_id");
        $this->diyModelService->duplicateDesign($design_id);
        return $this->successJson('ok');
    }

    public function renameDesign(Request $request)
    {
        $design_id = $request->input("design_id");
        $name = $request->input("name");
        $this->diyModelService->renameDesign($design_id,$name);
        return $this->successJson('ok');
    }

    public function deleteDesign(Request $request)
    {
        $design_id = $request->input("design_id");
        $this->diyModelService->deleteDesign($design_id);
        return $this->successJson('ok');
    }

    public function designRecycleList(Request $request)
    {
        $search = $request->input("search",[]);

        $data  =  $this->diyModelService->designRecycleList($search);
        return $this->successJson('ok',$data);
    }

    public function restoreDesign(Request $request)
    {
        $design_id = $request->input("design_id");
        $this->diyModelService->restoreDesign($design_id);
        return $this->successJson('ok');
    }

    public function deleteDesignReal(Request $request)
    {
        $design_id = $request->input("design_id");
        $this->diyModelService->deleteDesignReal($design_id);
        return $this->successJson('ok');

    }

    public function uploadDwg(Request $request)
    {
       
        $data = $this->diyModelService->uploadDwg();
        return $this->successJson('ok',$data);
    }

    public function saveDesign(Request $request)
    {

        $data = $this->diyModelService->saveDesign($request->input());
        return $this->successJson('ok',$data);
    }

    public function getDesignDetail(Request $request)
    {
        $design_id = $request->input("id")?:0;
        $data = $this->diyModelService->getDesignDetail($design_id);
        return $this->successJson('ok',$data);
    }

    public function getSearchCategory(Request $request)
    {
        $data = $this->diyModelService->getSearchCategory();
        return $this->successJson('ok',$data);
    }

    public function getSearchPlace(Request $request)
    {
        $data = $this->diyModelService->getSearchPlace();
        return $this->successJson('ok',$data);
    }

    public function getMyCollect(Request $request)
    {
        $data = $this->diyModelService->getMyCollect();
        return $this->successJson('ok',$data);
    }

    public function checkOverlap(Request $request)
    {
        $project_id = $request->input("project_id",0);
  
        $project_id = $project_id =="undefined" ? 0 : $project_id;
        $data = $this->diyModelService->checkOverlap($project_id);    
        return $this->successJson('ok',$data);
    }

    public function getRecommendIndustry(Request $request)
    {
        $data = $this->diyModelService->getRecommendIndustry();
        return $this->successJson('ok',$data);
    }

    public function getIssueOptions(Request $request)
    {
        $data = $this->diyModelService->getIssueOptions();
        return $this->successJson('ok',$data);
    }
    
    public function submitIssue(Request $request)
    {
        $this->validate([
           // 'content' => 'required',
            'issue_id' => 'required',
            'issue_parent' => 'required|array', // 添加 array 验证规则
            'thumb_url' => 'required|array',    // 添加 array 验证规则
        ],$request, [
           // 'content.required' => '请输入描述的问题',
            'issue_id.required' => '请选择问题类型',
            'issue_parent.required' => '请选择问题内容类型',
            'thumb_url.required' => '请上传图片',

        ]);
        
        $data = $this->diyModelService->submitIssue($request->input());
        return $this->successJson('ok',$data);
    }

    public function getGoodsStatus(Request $request)
    {
        //验证参数
        $this->validate([
            'goodsIds' => 'required|array',
        ], $request, [
            'goodsIds.required' => '请选择商品',
            'goodsIds.array' => '商品参数必须是数组',
        ]);
        $goodsIds = $request->input('goodsIds', []);
        $data = $this->diyModelService->getGoodsStatus($goodsIds);
        return $this->successJson('ok', $data);
    }

}