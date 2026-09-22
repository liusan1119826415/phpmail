<?php


namespace app\backend\modules\goods\services;

use app\backend\modules\goods\models\PptTemplate;
use app\common\exceptions\ShopException;

class PptService
{


    public function getList($search)
    {
        $query = PptTemplate::select("id","name","thumb","created_at");
        if($search['name']){
            $query->where('name','like','%'.$search['name'].'%');
        }

        $list = $query->orderBy('id','desc')->paginate(20);
        return $list;

    }

    public function add($addData):bool
    {
        $StyleModel = new PptTemplate();
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
                    throw new ShopException('风格创建失败');
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
            $style = PptTemplate::find($id);
            if(!$style) {
                throw new ShopException('无此模版或已经删除');
            }
            $style->delete();
        }catch (\Exception $e){
            throw new ShopException('删除失败');
        }
        return true;
    }
}