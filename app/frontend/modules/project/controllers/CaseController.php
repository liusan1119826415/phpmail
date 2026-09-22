<?php


namespace app\frontend\modules\project\controllers;

use app\common\components\ApiController;
use app\frontend\modules\project\services\CaseService;
use Illuminate\Http\Request;

/**
 * 行业案例
 * Class CaseController
 * @package app\frontend\modules\project\controllers
 */
class CaseController extends ApiController
{
    private CaseService $caseService;

    protected $publicAction = ["getList"];
    protected $ignoreAction = ["getList"];

    public function __construct(CaseService $caseService)
    {
        $this->caseService = $caseService;
        parent::__construct();
    }

    public function getList(Request $request)
    {
        $search =$request->input('search',[]);
        $list = $this->caseService->getList($search);
        return $this->successJson('ok',$list);
    }



    public function getCaseLable(Request $request)
    {

        $list = $this->caseService->getCaseLable();
        return $this->successJson('ok',$list);
    }

    public function toggleFavorite(Request $request)
    {
        $this->validate([
            'id' => 'required|integer|min:0',
        ]);
        $id = $request->input('id');
        $this->PreventDuplicateSubmission($request);
        $this->caseService->toggleFavorite($id);
        return $this->successJson('ok');
    }

    public function detail(Request $request)
    {
        $this->validate([
            'id' => 'required|integer|min:0',
        ]);
        $id = $request->input('id');
        $data = $this->caseService->detail($id);
        return $this->successJson('ok',$data);
    }






}