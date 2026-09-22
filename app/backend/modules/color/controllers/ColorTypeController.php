<?php
namespace app\backend\modules\color\controllers;


use app\backend\modules\goods\models\Category;
use app\backend\modules\uploadVerificate\UploadVerificationBaseController;
use app\common\models\color\ColorType;
use Setting;
/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/2/27
 * Time: 上午9:17
 */
class ColorTypeController extends UploadVerificationBaseController
{
    /**
     * 色板类型列表
     */
    public function index()
    {

        return view('color.color_type.list')->render();
    }


    public function getBrandData(){
        $where = [];

        if (request()->search_brand_keyword){
            $where[] = ['name','like','%'.trim(request()->search_brand_keyword).'%'];
        }

        $data = \app\common\models\Brand::getBrands()->where($where)->select('id', 'name')->get();

        if ($data->isNotEmpty()){
            $data->each(function (&$v){
                if (mb_strlen($v->name,'utf-8') > 40){
                    $v->name = mb_substr($v->name,0,40,'utf-8').'...';
                }
            });
        }

        return $this->successJson('获取成功',$data);

    }

    public function colorData()
    {
        $search = request()->search;
        $pageSize = 20;
        $list = ColorType::getColorType($search)->with(['belongsToCategory'=>function($query) {
            $query->select("id","name");
        }])->orderBy('id','desc')->paginate($pageSize);
        return $this->successJson('ok',$list);

    }


    /**
     * 添加品牌
     */
    public function add()
    {
        $colorTypeModel = new ColorType();

        $requestColorType = request()->color_type;

        if($requestColorType) {
            $requestColorType['category_ids'] = implode(",",$requestColorType['category_ids']);
            //将数据赋值到model
            $colorTypeModel->setRawAttributes($requestColorType);
            //其他字段赋值
            $colorTypeModel->uniacid = \YunShop::app()->uniacid;

            //字段检测
            $validator = $colorTypeModel->validator($colorTypeModel->getAttributes());
            if ($validator->fails()) {//检测失败
                $this->errorJson($validator->messages());
            } else {
                //数据保存
                if ($colorTypeModel->save()) {
                    //显示信息并跳转
                    return $this->successJson('色板类型创建成功');
                }else{
                    $this->errorJson('色板类型创建失败');
                }
            }
        }

        $this->title = '创建色板类型';
        $this->breadcrumbs = [
            '色板类型管理'=>['url'=>$this->createWebUrl('color.color_type.index'),'icon'=>'icon-dian'],
            $this->title,
        ];

        return $this->successJson('ok',$colorTypeModel);
    }

    public function editViwe()
    {

        $shopset = Setting::get('shop.category');
        $category = Category::parentGetCategorys()->get();
        return view('color.color_type.info', [
            'id' => request()->id,
            'cat_level'=>$shopset['cat_level'],
            'category'=>$category
        ])->render();
    }

    /**
     * 编辑商品品牌
     */
    public function edit()
    {

        $colorTypeModel = ColorType::where("id",request()->id)->first();
        if(!$colorTypeModel){
            return $this->errorJson('无此记录或已被删除');
        }
        $requestBrand = request()->color_type;
        if($requestBrand) {
            //将数据赋值到model
            $requestBrand['category_ids'] = implode(",",$requestBrand['category_ids']);
            $colorTypeModel->setRawAttributes($requestBrand);
            //字段检测
            $validator = $colorTypeModel->validator($colorTypeModel->getAttributes());
            if ($validator->fails()) {//检测失败
                $this->errorJson($validator->messages());
            } else {
                //数据保存
                if ($colorTypeModel->save()) {
                    //显示信息并跳转
                    return $this->successJson('色板类型保存成功');
                }else{
                    $this->errorJson('色板类型保存失败');
                }
            }
        }
        $category_ids = explode(",",$colorTypeModel->category_ids);
        if($category_ids){
            $colorTypeModel->category_list = Category::select("id","name")->whereIn('id',$category_ids)->get();
        }
        return $this->successJson('ok',$colorTypeModel);
    }

    /**
     * 删除商品品牌
     */
    public function deleted()
    {
        if (request()->ids) {
            $brand = ColorType::whereIn('id', request()->ids);

            $result = $brand->delete();
        } else {
            $brand = ColorType::where("id",request()->id)->first();
            if(!$brand) {
                return $this->errorJson('无此色板类型或已经删除');
            }
            $result = ColorType::deletedColorType(request()->id);
        }

        if($result) {
           return $this->successJson('删除色板类型成功');
        }else{
            return $this->errorJson('删除色板类型失败');
        }
    }

    /**
     * 商品品牌
     */
    public function searchBrand()
    {
        $keyword = request()->keyword;

        if (!$keyword)
        {
            return $this->errorJson('请输入关键字!!');
        }
        $brand = Brand::keywordGetBrand($keyword)->limit(20)->get()->toArray();
        return $this->successJson('ok',$brand);
    }


}