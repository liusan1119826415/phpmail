<?php


namespace app\backend\modules\goods\services;

use app\backend\modules\goods\models\Style;
use app\common\exceptions\ShopException;

class StyleService
{


    public function getList($search)
    {
        $query = Style::select("id","name","created_at");
        if($search['name']){
            $query->where('name','like','%'.$search['name'].'%');
        }

        $list = $query->where('type',$search['type'])->orderBy('id','desc')->paginate(15);
        return $list;

    }

    public function add($addData):bool
    {
        $StyleModel = new Style();
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


    public function getJson($type)
    {
        $query = Style::select("id","name")->where('type',$type);
        if($type == 1){
            $query->orderBy('id','desc');
        }
        return $query->get()->toArray();
    }


    public function delete($id):bool
    {
        try {
            $style = Style::find($id);
            if(!$style) {
                throw new ShopException('无此风格或已经删除');
            }
            $style->delete();
        }catch (\Exception $e){
            throw new ShopException('删除失败');
        }
        return true;
    }
}