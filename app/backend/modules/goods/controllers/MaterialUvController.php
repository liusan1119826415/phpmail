<?php

namespace app\backend\modules\goods\controllers;


use app\common\models\project\MaterialUv;
use app\backend\modules\uploadVerificate\UploadVerificationBaseController;
use app\backend\modules\goods\services\MaterialService;
use Illuminate\Http\Request;

/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/2/27
 * Time: 上午9:17
 */
class MaterialUvController extends UploadVerificationBaseController
{


    private MaterialService $materialService;


    public function __construct(MaterialService $materialService)
    {
        $this->materialService = $materialService;
    }

    /**
     * uv列表
     */
    public function index()
    {
        return view('goods.material-uv.list')->render();
    }


    public function getData(Request $request)
    {
        $search = $request->search;
        $search['type'] = 2;
        $data = $this->materialService->getList($search);
        return $this->successJson('获取成功', $data);

    }


    /**
     * 添加品牌
     */
    public function add(Request $request)
    {
        $data = $request->input('style');

        $this->materialService->add($data);
        return $this->successJson('ok');
    }

    public function editView()
    {
        return view('goods.material-uv.info', [
            'id' => request()->id
        ])->render();
    }

    /**
     * 编辑商品品牌
     */
    public function edit(Request $request)
    {

        $styleModel = MaterialUv::find($request->id);
        if (!$styleModel) {
            return $this->errorJson('无此记录或已被删除');
        }
        $style = $request->style;

        if ($style) {

            $this->materialService->edit($styleModel,$style);
            return $this->successJson('ok');
        }

        return $this->successJson('ok', $styleModel);
    }




    /**
     * 删除
     */
    public function delete(Request $request)
    {
        $this->materialService->delete($request->id);
        return $this->successJson("ok");
    }


}