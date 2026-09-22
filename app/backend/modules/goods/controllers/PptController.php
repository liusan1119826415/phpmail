<?php

namespace app\backend\modules\goods\controllers;


use app\backend\modules\goods\models\PptTemplate;
use app\backend\modules\goods\models\Style;
use app\backend\modules\uploadVerificate\UploadVerificationBaseController;
use app\backend\modules\goods\services\PptService;
use Illuminate\Http\Request;

/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/2/27
 * Time: 上午9:17
 */
class PptController extends UploadVerificationBaseController
{


    private PptService $pptService;


    public function __construct(PptService $pptService)
    {
        $this->pptService = $pptService;
    }

    /**
     * 商品品牌列表
     */
    public function index()
    {
        return view('goods.ppt.list')->render();
    }


    public function getData(Request $request)
    {
        $search = $request->search;
        $data = $this->pptService->getList($search);
        $data = $data->toArray();
        foreach ($data['data'] as $key=>$item){
            $data['data'][$key]['thumb'] = yz_tomedia($item['thumb']);
        }
        return $this->successJson('获取成功', $data);

    }


    /**
     * 添加品牌
     */
    public function add(Request $request)
    {
        $data = $request->input('style');
        $this->pptService->add($data);
        return $this->successJson('ok');
    }

    public function editView()
    {
        return view('goods.ppt.info', [
            'id' => request()->id
        ])->render();
    }

    /**
     * 编辑商品品牌
     */
    public function edit(Request $request)
    {

        $styleModel = PptTemplate::find($request->id);
        if (!$styleModel) {
            return $this->errorJson('无此记录或已被删除');
        }
        $style = $request->style;

        if ($style) {
            $this->pptService->edit($styleModel,$style);
            return $this->successJson('ok');
        }
        $styleModel->thumb_data = [
            [
                "url"=>yz_tomedia($styleModel->url),
                "attachment"=>$styleModel->url,
                "filename"=>basename($styleModel->url)
            ]
        ];
        $styleModel->thumb_url = yz_tomedia($styleModel->thumb);
        return $this->successJson('ok', $styleModel);
    }




    /**
     * 删除
     */
    public function delete(Request $request)
    {
        $this->pptService->delete($request->id);
        return $this->successJson("ok");
    }


}