<?php

namespace app\backend\modules\cloud\controllers;



use app\common\components\BaseController;
use app\common\exceptions\ShopException;
use Illuminate\Http\Request;
use app\backend\modules\industry\models\CaseLable;
use app\backend\modules\cloud\services\CaseRecommendService;
use app\common\models\project\IssueOptions;

class CaseRecommendController extends BaseController
{
    private CaseRecommendService $case_recommend;


    public function __construct(CaseRecommendService $case_recommend)
    {
        $this->case_recommend = $case_recommend;
    }

    public function index()
    {

        return view('cloud.caseRecommend.list')->render();
    }


    public function getData(Request $request)
    {
        $search = $request->search;
        $data = $this->case_recommend->getList($search);
        return $this->successJson('获取成功', $data);
    }

    /**
     * 案例详情
     */

    public function detail(Request $request)
    {
        $id = $request->id;
        if ($request->ajax()) {
            $data = $this->case_recommend->getDetail($id);
            return $this->successJson('获取成功', $data);
        }

        $CaseLable = CaseLable::select("id","name")->get();

        return view('cloud.caseRecommend.info', ['id' => $id,'CaseLable'=>$CaseLable])->render();
    }

    /**
     * 推荐案例
     */
    public function recommend(Request $request)
    {
        $id = $request->id;
        $is_suggested = $request->is_suggested;
        $this->case_recommend->recommend($id, $is_suggested);
        return $this->successJson('操作成功');
    }
}
