<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/3/3
 * Time: 下午4:30
 */

namespace app\backend\modules\goods\controllers;


use app\backend\modules\goods\models\Category;
use app\common\models\project\InstallExpress;
use app\common\components\BaseController;
use app\common\models\Address;

class InstallController extends BaseController
{
    /**
     * 物流模版
     * @return array $item
     */
    public function index()
    {
        return view('goods.install.list', [])->render();
    }



    public function dispatchData()
    {

        $pageSize = 15;
        $list = InstallExpress::with('category')->paginate($pageSize)->toArray();
        return $this->successJson('ok', $list);
    }

    public function editSave()
    {

        $category_data = Category::where('parent_id',0)->where('enabled',1)->get();
        $cat_level = \Setting::get('shop.category')['cat_level'];
        $province_data = Address::where('parentid',0)->get();
        return view('goods.install.info', [
            'id' => request()->id,'category_data'=>$category_data,'cat_level'=>$cat_level,'province_data'=>$province_data
        ])->render();
    }

    /**
     * 配送模板添加
     * @return array $item
     */
    public function add()
    {


        $form = request()->form;

        if ($form) {

            $Logistics = InstallExpress::where('category_id',$form['category_id'])->first();
            if(!$Logistics){
                $logistics = new InstallExpress();
            }

             $form['freight'] = !empty($form['freight'])?serialize($form['freight']):serialize([]);
             $form['category_ids'] = implode(",",$form['category_ids']);


            //将数据赋值到model
            $logistics->setRawAttributes($form);

            //字段检测
            $validator = $logistics->validator($logistics->getAttributes());
            if ($validator->fails()) {//检测失败
                $this->errorJson($validator->messages());
            } else {

                //数据保存
                if ($logistics->save()) {
                    //显示信息并跳转
                    return $this->successJson('创建成功');
                } else {
                    return $this->errorJson('创建失败');
                }
            }
        }

        $id = request()->input('id');
        $data = InstallExpress::where('id',$id)->first();
        $data->freight = $data->freight?unserialize($data->freight):[];
        $data->category_ids = !empty($data->category_ids)
            ? array_map('intval', explode(",", $data->category_ids))
            : [];

        return $this->successJson('ok',$data);

    }

    /**
     * 配送模板编辑
     * @return array $item
     */
    public function edit()
    {
        $form = request()->form;
        $id = request()->id;
        if ($form) {

            $logistics = InstallExpress::find($id);
            $form['freight'] = !empty($form['freight'])?serialize($form['freight']):serialize([]);
            $form['category_ids'] = implode(",",$form['category_ids']);
            //将数据赋值到model
            $logistics->setRawAttributes($form);

            //字段检测
            $validator = $logistics->validator($logistics->getAttributes());
            if ($validator->fails()) {//检测失败
                $this->errorJson($validator->messages());
            } else {

                //数据保存
                if ($logistics->save()) {
                    //显示信息并跳转
                    return $this->successJson('创建成功');
                } else {
                    return $this->errorJson('创建失败');
                }
            }
        }

        $id = request()->input('id');
        $data = InstallExpress::where('id',$id)->first();
        $data->freight = $data->freight?unserialize($data->freight):[];
        $data->category_ids = !empty($data->category_ids)
            ? array_map('intval', explode(",", $data->category_ids))
            : [];

        return $this->successJson('ok',$data);


    }

    /**
     * 配送模板删除
     * @return array $item
     */
    public function delete()
    {

        $model = InstallExpress::find(request()->id);
        if (!$model) {
            return $this->errorJson('找不到数据');
        }

        if ($model->delete()) {
            return $this->successJson('删除成功');
        } else {
            return $this->errorJson('删除失败');
        }
    }


    public function quickEdit()
    {
        $id = request()->id;
        $type = request()->type;
        $status = request()->status;

        $result = InstallExpress::quickUpdatedDispatch($id, $type, $status);

        if ($result) {
            return $this->successJson('修改成功');
        } else {
            return $this->errorJson('修改失败');
        }
    }



}
