<?php

namespace app\frontend\modules\project\controllers;

use app\common\components\ApiController;
use app\common\exceptions\AppException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use app\frontend\modules\project\services\GoodsSearchService;
use Illuminate\Support\Facades\DB;
use app\Jobs\DispatchesJobs;
class GoodsSearchTestController extends ApiController
{


    protected $publicAction = ["reindexAll","delete"];
    protected $ignoreAction = ["reindexAll","delete"];

    /**
     *创建我的项目
     */

    private GoodsSearchService $goodsSearchService;

    public function __construct(GoodsSearchService $goodsSearchService)
    {
        $this->goodsSearchService = $goodsSearchService;
        parent::__construct();
    }


    public function search(Request $request)
    {

        $search = $request->input("search");
        $data = $this->goodsSearchService->search($search);
        return $this->successJson('ok',$data);
    }

    public function delete()
    {
        $data = $this->goodsSearchService->deleteIndex("goods_option");
        return $this->successJson('ok',$data);
    }

    public function reindexAll()
    {

    

        // DB::beginTransaction();
        // try {

           
           
        //    DB::commit();
           
        // } catch (\Exception $e) {
        //     DB::rollBack();
        //     throw new AppException('刷新失败');
        // }

        // echo "开始刷新";die;

        // $goods = \app\common\models\Goods::whereIn('supp_id',[42,54])->get();

        // foreach ($goods as $good) {
        //     $atlas = $good->atlas?json_decode($good->atlas,true):[];
          
        //     $atlas['isPdf'] = 0;
        //     $atlasJson = json_encode($atlas);
        //     $good->atlas = $atlasJson;
        //     $good->save();
        // }

        // echo "刷新成功";die;



        $this->goodsSearchService->reindexAll();
        return $this->successJson('ok');
    }

    public function updateData()
    {
        $this->goodsSearchService->updateData();
        return $this->successJson('ok');
    }

    public function search_goods_category(Request $request)
    {
        $search = $request->input("search");
        $data = $this->goodsSearchService->search_goods_category($search);
        return $this->successJson('ok',$data);
    }

    public function search_goods_model(Request $request)
    {
        $search = $request->input("search");
        $data = $this->goodsSearchService->search_goods_model($search);
        return $this->successJson('ok',$data);
    }

    public function getSearchData(Request $request)
    {
        $search_type = $request->input("search_type");
        $data = $this->goodsSearchService->getSearchData($search_type);
        return $this->successJson('ok',$data);
    }



}