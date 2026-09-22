<?php

namespace app\backend\modules\goods\controllers;


use app\backend\modules\goods\models\Style;
use app\backend\modules\uploadVerificate\UploadVerificationBaseController;
use app\backend\modules\goods\services\StyleService;
use Illuminate\Http\Request;

/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/2/27
 * Time: 上午9:17
 */
class MaterialController extends UploadVerificationBaseController
{


    private StyleService $styleService;


    public function __construct(StyleService $styleService)
    {
        $this->styleService = $styleService;
    }

    /**
     * 商品品牌列表
     */
    public function index()
    {
        return view('goods.material.list')->render();
    }


    public function getData(Request $request)
    {
        $search = $request->search;
        $search['type'] = 2;
        $data = $this->styleService->getList($search);
        return $this->successJson('获取成功', $data);

    }


    /**
     * 添加品牌
     */
    public function add(Request $request)
    {
        $data = $request->input('style');
        $data['type'] = 2;
        $this->styleService->add($data);
        return $this->successJson('ok');
    }

    public function editView()
    {
        return view('goods.material.info', [
            'id' => request()->id
        ])->render();
    }

    /**
     * 编辑商品品牌
     */
    public function edit(Request $request)
    {

        $styleModel = Style::find($request->id);
        if (!$styleModel) {
            return $this->errorJson('无此记录或已被删除');
        }
        $style = $request->style;

        if ($style) {
            $style['type'] = 2;
            $this->styleService->edit($styleModel,$style);
            return $this->successJson('ok');
        }

        return $this->successJson('ok', $styleModel);
    }


    public function getJson(Request $request)
    {
        $list = $this->styleService->getJson(2);
        return $this->successJson('ok',$list);
    }

    /**
     * 删除
     */
    public function delete(Request $request)
    {
        $this->styleService->delete($request->id);
        return $this->successJson("ok");
    }


}