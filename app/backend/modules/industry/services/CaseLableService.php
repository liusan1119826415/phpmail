<?php


namespace app\backend\modules\industry\services;


use app\backend\modules\industry\models\CaseLable;
use app\common\exceptions\ShopException;

class CaseLableService
{


    public function getList($search)
    {

        $query = CaseLable::select("id","name","created_at");
        if($search['name']){
            $query->where('name','like','%'.$search['name'].'%');
        }

        $list = $query->orderBy('id','desc')->paginate(20);
        return $list;

    }


    public function add($addData):bool
    {
        $StyleModel = new CaseLable();
        if($addData) {
            //将数据赋值到model
            $StyleModel->setRawAttributes($addData);
            //字段检测
            $validator = $StyleModel->validator($StyleModel->getAttributes());
            if ($validator->fails()) {//检测失败
                throw new ShopException($validator->messages());
            } else {
                //数据保存
                if ($StyleModel->save()) {
                    //显示信息并跳转
                    return true;
                }else{
                    throw new ShopException('创建失败');
                }
            }
        }
    }

    public function edit($StyleModel,$data):bool
    {

        if($data) {
            //将数据赋值到model
            $StyleModel->setRawAttributes($data);
            //字段检测
            $validator = $StyleModel->validator($StyleModel->getAttributes());
            if ($validator->fails()) {//检测失败
                throw new ShopException($validator->messages());
            } else {
                //数据保存
                if ($StyleModel->save()) {
                    //显示信息并跳转
                    return true;
                }else{
                    throw new ShopException('修改成功');
                }
            }
        }
    }





    public function delete($id):bool
    {
        try {
            $style = CaseLable::find($id);
            if(!$style) {
                throw new ShopException('无此Lable或已经删除');
            }
            $style->delete();
        }catch (\Exception $e){
            throw new ShopException('删除失败');
        }
        return true;
    }
}