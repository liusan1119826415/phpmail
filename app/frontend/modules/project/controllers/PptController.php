<?php


namespace app\frontend\modules\project\controllers;


use app\common\components\ApiController;
use app\frontend\modules\project\services\PptService;
use Illuminate\Http\Request;

class PptController extends ApiController
{

    private PptService $pptService;

    public function __construct(PptService $pptService)
    {
        $this->pptService = $pptService;
        parent::__construct();
    }

    public function getTemplate()
    {
        $list = $this->pptService->getTemplate();
        return $this->successJson('ok',$list);
    }

    public function generatePpt(Request $request)
    {


        $this->validate([
            'space_ids' => 'required|array',
            'space_ids.*' => 'integer|min:0',
            'project_name' => 'required|string',
            'template_id' => 'required|integer|min:0',
            'project_id'=>'required|integer|min:0',
        ]);
        $space_ids = $request->input('space_ids');
        $project_name = $request->input('project_name');
        $template_id = $request->input('template_id');
        $project_id = $request->input('project_id');
        $result = $this->pptService->generatePpt($space_ids,$project_name,$template_id,$project_id);
        return $this->successJson('ok',$result);
    }



    public function export(Request $request)
    {
        $this->validate([
            'id'=>'required|integer|min:0',
        ]);
        $id = $request->input('id');
        $result = $this->pptService->export($id);
        return $this->successJson('ok',$result);

    }


    public function savePpt(Request $request)
    {
        $this->validate([
            'id'=>'required|integer|min:0',
        ]);
        $id = $request->input('id');
        $project_name = $request->input('project_name');
        $result = $this->pptService->savePpt($id,$project_name);
        return $this->successJson('ok',$result);

    }

    public function delete(Request $request)
    {
        $this->validate([
            'id'=>'required|integer|min:0',
        ]);
        $id = $request->input('id');
        $this->pptService->delete($id);
        return $this->successJson('ok');

    }



}