<?php

namespace app\backend\modules\goods\controllers;


use app\backend\modules\goods\models\Style;
use app\backend\modules\uploadVerificate\UploadVerificationBaseController;
use app\backend\modules\goods\services\BaseMaterialService;
use app\common\exceptions\ShopException;
use app\common\models\project\MaterialUv;
use app\common\services\Session;
use Illuminate\Http\Request;
use Yunshop\Supplier\common\models\SupplierColorCategory;
use Yunshop\Supplier\common\models\SupplierColorPlane;

/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/2/27
 * Time: 上午9:17
 */
class BaseMaterialController extends UploadVerificationBaseController
{


    private BaseMaterialService $baseMaterialService;


    public function __construct(BaseMaterialService $baseMaterialService)
    {
        $this->baseMaterialService = $baseMaterialService;
    }

    /**
     * 商品品牌列表
     */
    public function index()
    {
        return view('goods.base-material.list')->render();
    }


    public function getData(Request $request)
    {
        $search = $request->search;
        $data = $this->baseMaterialService->getList($search);
        $data->transform(function ($item) {
            $item->thumb = yz_tomedia($item->thumb);
            return $item;
        });
        return $this->successJson('获取成功', $data);

    }


    /**
     * 添加品牌
     */
    public function add(Request $request)
    {
        $data = $request->input('brand');

        $this->baseMaterialService->add($data);
        return $this->successJson('ok');
    }

    public function editView()
    {

        $mtype = request()->mtype;
        if($mtype == "add"){
            $view = "goods.base-material.info";
        }else{
            $view = "goods.base-material.edit";
        }
        $supplier_id = 0;
        $uv_list = MaterialUv::select("id","name")->get();
        return view($view, [
            'id' => request()->id,
            "uv_list"=>$uv_list
        ])->render();
    }

    /**
     * 编辑商品品牌
     */
    public function edit(Request $request)
    {

        $brandModel = SupplierColorPlane::getInfo(request()->id);
        if (!$brandModel) {
            return $this->errorJson('无此记录或已被删除');
        }
        $requestBrand = request()->brand;
        $dtype = request()->dtype;
        if($dtype == "editname"){
            $brandModel->name = preg_replace('/\.(jpg|jpeg|png|gif|bmp|webp|svg|ico)$/i', '', request()->name);
            $brandModel->save();
            return $this->successJson('保存成功');
        }
        if ($requestBrand) {
            //将数据赋值到model
            $supplier_color_category = SupplierColorCategory::where('supplier_id',0)->first();
            $requestBrand['cate_ids'] = $supplier_color_category->id;
            $requestBrand['name'] = preg_replace('/\.(jpg|jpeg|png|gif|bmp|webp|svg|ico)$/i', '', $requestBrand['name']);
            $brandModel->setRawAttributes($requestBrand);
            //字段检测
            $validator = $brandModel->validator($brandModel->getAttributes());
            if ($validator->fails()) {//检测失败
                $this->errorJson($validator->messages());
            } else {
                //数据保存
                if ($brandModel->save()) {
                    //显示信息并跳转
                    return $this->successJson('保存成功');
                } else {
                    $this->errorJson('保存失败');
                }
            }
        }

        $brandModel->thumb_url = yz_tomedia($brandModel->thumb);

        return $this->successJson('ok', $brandModel);
    }



    /**
     * 删除
     */
    public function delete(Request $request)
    {
        $this->baseMaterialService->delete($request->id);
        return $this->successJson("ok");
    }

    public function batchDelete(Request $request)
    {
        try {
            SupplierColorPlane::whereIn('id',$request->ids)->delete();
            return $this->successJson("ok");
        }catch (\Exception $e){
            throw new ShopException($e->getMessage());
        }
    }


}