<?php

namespace app\backend\modules\industry\controllers;

use app\backend\modules\industry\models\IndustryCase;
use app\common\components\BaseController;
use Illuminate\Http\Request;

class IndustryCaseController extends BaseController
{


    public function index()
    {

        return view('industry.industry_lable.list')->render();
    }


    public function getData(Request $request)
    {
        $search = $request->search;
        $data = $this->caseLableService->getList($search);
        $data = $data->toArray();
        return $this->successJson('获取成功', $data);

    }


    /**
     * 添加品牌
     */
    public function add(Request $request)
    {
        $data = $request->input('style');
        $this->caseLableService->add($data);
        return $this->successJson('ok');
    }



    /**
     * 编辑商品品牌
     */
    public function edit(Request $request)
    {

        $styleModel = CaseLable::find($request->id);
        if (!$styleModel) {
            return $this->errorJson('无此记录或已被删除');
        }
        $style = $request->style;

        if ($style) {
            $this->caseLableService->edit($styleModel,$style);
            return $this->successJson('ok');
        }

        return $this->successJson('ok', $styleModel);
    }




    /**
     * 删除
     */
    public function delete(Request $request)
    {
        $this->caseLableService->delete($request->id);
        return $this->successJson("ok");
    }

}