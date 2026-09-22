<?php


namespace app\frontend\modules\project\controllers;

use app\backend\modules\goods\services\ExtractImageVector;
use app\common\components\ApiController;
use app\common\services\wechat\lib\WxPayApi;
use app\common\services\wechat\lib\WxPayConfig;
use app\common\services\wechat\WechatScanPayService;
use Illuminate\Http\Request;
use app\frontend\modules\project\services\SpaceService;
use app\Jobs\HighPptJob;
use app\Jobs\DispatchesJobs;
class SpaceController extends ApiController
{
    public $transactionActions = ['addSpace','add3DSpace','delSpace'];

    protected $publicAction = ["test"];
    protected $ignoreAction = ["test"];

    private SpaceService $spaceService;

    public function __construct(SpaceService $spaceService)
    {
        $this->spaceService = $spaceService;
        parent::__construct();
    }



    public function test(){
//            $job = new HighPptJob();
//            DispatchesJobs::dispatch($job,DispatchesJobs::LOW);


        $res = WxPayApi::getTradeDetail("4200002363202410153840221511",new WxPayConfig());

        print_r($res);die;
    }

    public function addSpace(Request $request)
    {
        $this->validate([
            'goods_id' => 'required|integer|min:0',
            'project_id' => 'required|integer|min:0',
            'space_id' => 'required|integer|min:0',
            'total' => 'required|integer|min:0',
            'option_id' => 'integer|min:0',
        ]);
        $request_data = $request->input();
       // $this->PreventDuplicateSubmission($request);
        $data = $this->spaceService->addSpace($request_data);
        return $this->successJson('ok',$data);


    }



    public function add3DSpace(Request $request)
    {
        $this->validate([
            'project_id' => 'required|integer|min:0',
            'space_id' => 'required|integer|min:0',
            'total' => 'required|integer|min:0',
            'productType'=>'required|integer|min:0',  //模型类型
        ]);
        $request_data = $request->input();
        $data = $this->spaceService->add3DSpace($request_data);
        return $this->successJson('ok',$data);


    }

    public function updateNum(Request $request)
    {

        $this->validate([
            'id' => 'required|integer|min:0',
            'num' => 'integer',
        ]);
       // $this->PreventDuplicateSubmission($request);
        $id = $request->input('id');
        $num = $request->input('num');
        $data = $this->spaceService->updateSpaceNum($id,$num);
        return $this->successJson('ok',$data);

    }

    public function getProductList(Request $request)
    {
        $this->validate([
            'space_id' => 'required|integer|min:0',
        ]);
        $space_id = $request->input('space_id');
        $list = $this->spaceService->getProductList($space_id);
        return $this->successJson('ok',$list);
    }

    public function delSpace(Request $request){
        $this->validate([
            'space_id' => 'required|integer|min:0',
        ]);
        $space_id = $request->input('space_id');

        $data = $this->spaceService->del_space($space_id);
        return $this->successJson('ok',$data);

    }

    public function delGoods(Request $request)
    {
        $this->validate([
            'space_id' => 'required|integer|min:0',
            'ids' => 'required|array', // 验证 ids 必须是数组
            'ids.*' => 'integer|min:0', // 验证 ids 数组中的每个值必须是整数且大于等于 0
        ]);

        $ids = $request->input('ids');
        $space_id = $request->input('space_id');
        $data = $this->spaceService->destroy($ids,$space_id);
        return $this->successJson('ok',$data);
    }


    //创建新空间
    public function createSpace(Request $request)
    {
        $this->validate([
            'space_name' => 'required|string',
            'project_id' => 'required|integer|min:0',
            'floor_id' => 'required|integer|min:0',
        ]);
        $project_id = $request->input("project_id");
        $space_name = $request->input("space_name");
        $floor_id = $request->input("floor_id");
        $this->PreventDuplicateSubmission($request);
        $id = $this->spaceService->createSpace($project_id,$floor_id,$space_name);
        return $this->successJson('ok',['space_id'=>$id]);
    }

    //修改空间
    public function editSpace(Request $request)
    {
        $this->validate([
            'space_name' => 'required|string',
            'space_id' => 'required|integer|min:0',
        ]);
        $space_name = $request->input("space_name");
        $space_id = $request->input("space_id");
        $this->PreventDuplicateSubmission($request);
        $this->spaceService->editSpace($space_id,$space_name);
        return $this->successJson('ok');
    }


    public function getProductListV2(Request $request)
    {
        $this->validate([
            'space_id' => 'required|integer|min:0',
        ]);
        $space_id = $request->input('space_id');
        $list = $this->spaceService->getProductListV2($space_id);
        return $this->successJson('ok',$list);
    }


    public function customized(Request $request)
    {
        $this->validate([
            'id' => 'required|integer|min:0',
        ]);

        $this->spaceService->customized();
        return $this->successJson('ok');
    }


}