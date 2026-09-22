<?php


namespace app\frontend\modules\project\controllers;

use app\common\components\ApiController;
use app\frontend\modules\coupon\models\Goods;
use app\frontend\modules\goods\models\Comment;
use app\frontend\modules\project\services\GoodsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Response;
class GoodsController extends ApiController
{

//    protected $publicAction = ['getUrl',"conditions","search"];
//    protected $ignoreAction = ['getUrl',"conditions","search"];

    private GoodsService $goodsService;

    public function __construct(GoodsService $goodsService)
    {
        $this->goodsService = $goodsService;
        parent::__construct();
    }

    public function conditions()
    {
        $list = $this->goodsService->conditions();
        return $this->successJson('ok',$list);
    }

    public function search(Request $request)
    {
        $search = $request->input('search');
        $order_field = $request->input('order_field',[]);
        $list = $this->goodsService->search($search,$order_field);
        return $this->successJson('ok',$list);
    }

    public function previewList(Request $request)
    {
        $this->validate([
            'project_id' => 'required|integer|min:0',
        ]);
        $project_id = $request->input('project_id');
        $list = $this->goodsService->previewList($project_id);
        return $this->successJson('ok',$list);
    }

    //修改单价
    public function updateUnitPrice(Request $request)
    {
        $this->validate([
            'price' => 'required|numeric|min:0',
            'option_ids' => 'required|array',
            'option_ids.*' => 'integer|min:0',
            'method' => 'required|numeric',
            'space_id' => 'required|numeric|min:0',
        ]);
        $price = $request->input('price');
        $option_ids = $request->input('option_ids');
        $method = $request->input('method');
        $space_id = $request->input('space_id');
        $this->PreventDuplicateSubmission($request);
        $data = $this->goodsService->updateUnitPrice($option_ids,$price,$method,$space_id);
        return $this->successJson('ok',$data);

    }

    //

    //修改规格
    public function updateOption(Request $request)
    {
        $request_data = $request->input();
        $this->PreventDuplicateSubmission($request);
        $data = $this->goodsService->updateOption($request_data);
        return $this->successJson('ok',$data);

    }

    //解析cad

    public function analysis(Request $request)
    {


        $this->validate([
            'file' => 'required|mimes:dwg|max:90000',
        ]);
        $this->PreventDuplicateSubmission($request);
        $data = $this->goodsService->analysis();
        return $this->successJson('ok',$data);

    }


    public function analysisV2(Request $request)
    {


      /*  $this->validate([
            'file.*' => 'required|mimes:dwg|max:90000',

        ]);*/
        $this->PreventDuplicateSubmission($request);
        $data = $this->goodsService->analysisV2();
        return $this->successJson('ok',$data);

    }

    public function getGoodsOption(Request $request)
    {
        $this->validate([
            'goods_id' => 'required|integer|min:0',
        ]);
        $goods_id = $request->input('goods_id');
        $data = $this->goodsService->getGoodsOption($goods_id);
        return $this->successJson('ok',$data);
    }

    public function getGoodsInfo(Request $request)
    {


        $this->validate([
            'goods_id' => 'required|integer|min:0',
        ]);

        $goods_id = $request->input('goods_id');
        $goods = Goods::find($goods_id);
        if(!$goods || $goods->status == 0){
            return response()->json([
                'result' => -2,
                'msg' => "商品已下架或者已删除",
                'data' => []
            ], 200, ['charset' => 'utf-8'])->send();
        }
        $data = $this->goodsService->getGoodsInfo($goods_id);
        return $this->successJson('ok',$data);
    }


    public function getComment()
    {
        $data = $this->goodsService->getComment();
        return $this->successJson('ok',$data);
    }






}