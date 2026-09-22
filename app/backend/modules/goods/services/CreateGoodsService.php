<?php
/**
 * Created by PhpStorm.
 * Author:  
 * Date: 2017/5/2
 * Time: 上午11:51
 */

namespace app\backend\modules\goods\services;

use app\backend\modules\goods\models\GoodsParam;
use app\backend\modules\goods\models\Goods;
use app\backend\modules\goods\models\Brand;
use app\backend\modules\goods\models\GoodsSpec;
use app\backend\modules\goods\models\GoodsOption;
use app\backend\modules\goods\models\GoodsStyleRelations;
use app\backend\modules\goods\models\GoodsVideo;
use app\common\events\goods\GoodsCreateEvent;
use app\common\exceptions\ShopException;
use app\common\models\goods\GoodsImages;
use Setting;

class CreateGoodsService
{
    public $params;
    public $brands;
    public $request;
    public $error = null;
    public $catetory_menus;


    /**
     * @var \app\common\models\Goods
     */
    public $goods_model;
    public $type;

    public $is_type;

    public function __construct($request, $type = 0)
    {
        $this->type = $type;
        $this->request = $request;


    }

    public function create()
    {

        $goods_data = $this->request->goods;

        $this->goods_model = $this->getGoodsModel();

        if ($goods_data) {
            if (empty($goods_data['content'])) {
                throw new ShopException("请填写商品描述");
            }
            //正则匹配富文本更改图片标签
            if ($goods_data['content']) {
                $goods_data['content'] = changeUmImgPath($goods_data['content']);
            }

            // 正则匹配富文本更改视频标签样式
            //$goods_data['content'] = preg_replace(htmlspecialchars('/<p[^>]*/'), htmlspecialchars('<p style="display: inline-block;"'), $goods_data['content']);
            $goods_data['content'] = preg_replace('/class="[^=]*/', 'class="edui-upload-video" controls', htmlspecialchars_decode($goods_data['content']));

            preg_match('/<video[^>]*/',  $goods_data['content'], $matches);

//            $matches[0] .= ' x5-playsinline="true" webkit-playsinline="true" playsinline="true"';
//
//            $goods_data['content'] = preg_replace('/<video[^>]*/', $matches[0], $goods_data['content']);

            $video_matche= '<video x5-playsinline="true" webkit-playsinline="true" playsinline="true" ';

            $goods_data['content'] = str_replace('<video', $video_matche, $goods_data['content']);
            $goods_data['content'] = htmlspecialchars($goods_data['content']);

            if ($this->type == 1) {
                $goods_data['status'] = 0;
            }
            if($goods_data['related_goods_id']){
                $goods_data['related_goods_id'] = implode(",",$goods_data['related_goods_id']);
            }else{
                $goods_data['related_goods_id'] = "";
            }
//            //商品视频地址
//            $goods_data['goods_video'] = yz_tomedia($goods_data['goods_video']);

            if (isset($goods_data['thumb_url'])) {
                $goods_data['thumb_url'] = serialize($goods_data['thumb_url']);
            }

            $goods_data['e_catalog_pdf'] = $goods_data['e_catalog_pdf']?serialize($goods_data['e_catalog_pdf']):serialize([]);

            $goods_data['maintenance_doc'] = $goods_data['maintenance_doc']?serialize($goods_data['maintenance_doc']):serialize([]);

            $goods_data['install_guide'] = $goods_data['install_guide']?serialize($goods_data['install_guide']):serialize([]);

            $goods_data['pdf_page'] = $goods_data['pdf_page']?serialize($goods_data['pdf_page']):serialize([]);
            $goods_data['wiring_diagram'] = $goods_data['wiring_diagram']?serialize($goods_data['wiring_diagram']):serialize([]);
            if (isset($goods_data['real_image'])) {
                $goods_data['real_image'] = serialize($goods_data['real_image']);
            }

            if($goods_data['structure'] == "undefined" || $goods_data['structure'] == ""){
                $goods_data['structure'] = "";
            }

            if($goods_data['design'] == "undefined" || $goods_data['design'] == ""){
                $goods_data['design'] = "";
            }

            if (!$goods_data['virtual_sales']) {
                $goods_data['virtual_sales'] = 0;
            }

            if (!empty($this->request->widgets['sale']['max_point_deduct'])
                && !empty($goods_data['price'])
                && $this->request->widgets['sale']['max_point_deduct'] > $goods_data['price']) {
                return ['status' => -1, 'msg' => '积分最大抵扣金额大于商品现价'];
            }
            if (!empty($this->request->widgets['sale']['min_point_deduct'])
                && !empty($goods_data['price'])
                && $this->request->widgets['sale']['min_point_deduct'] > $goods_data['price']) {
                return ['status' => -1, 'msg' => '积分最少抵扣金额大于商品现价'];
            }
            if(empty($goods_data['price'])){
                $goods_data['price'] = 0;
            }
            if(empty($goods_data['cost_price'])){
                $goods_data['cost_price'] = 0;
            }


            $goods_data['has_option'] = $this->request->widgets['option']['has_option'] ?: 0;
            $save_data = array_except($goods_data,['category','withhold_stock','video_image','goods_video','category_to_option','craft_materials','goods_style']);

            $this->goods_model->fill($save_data);

            $this->goods_model->widgets = $this->request->widgets;

            $this->setAfterHandle();
            $this->goods_model->uniacid = \YunShop::app()->uniacid;
            $this->goods_model->weight = $this->goods_model->weight ? $this->goods_model->weight : 0;

            $validator = $this->goods_model->validator($this->goods_model->getAttributes());
            if ($validator->fails()) {
                return ['status' => -1, 'msg' => $validator->messages()->first()];
            } else {
                if ($this->goods_model->save()) {
                    (new \app\common\services\operation\GoodsLog($this->goods_model, 'create'));
                    //商品视频保存
                    GoodsVideo::store($this->goods_model->id, array_only($goods_data,['video_image','goods_video']));

                    //商品场景图等保存
                    //GoodsImages::saveImages($goods_data['goods_images'],$this->goods_model->id);

                    //商品工艺材质保存
                    GoodsStyleRelations::saveStyle($this->goods_model->id,$goods_data['craft_materials'],2);
                    //商品风格保存
                    GoodsStyleRelations::saveStyle($this->goods_model->id,$goods_data['goods_style'],1);

                    //商品分类保存
                    GoodsService::store($this->goods_model->id, $goods_data['category'], \Setting::get('shop.category')['cat_level'],$goods_data['category_to_option']);

                    //商品属性保存
                    GoodsParam::store($this->goods_model->id,$this->request->widgets['param']);

                    //规格项和规格组合保存
                    SpecOptionService::store($this->goods_model->id,$this->request->widgets['option'],\YunShop::app()->uniacid);

//                    GoodsSpec::saveSpec($this->request, $this->goods_model->id, \YunShop::app()->uniacid);
//                    GoodsOption::saveOption($this->request, $this->goods_model->id, GoodsSpec::$spec_items, \YunShop::app()->uniacid);

                    //$this->upload_search_img($this->goods_model);
                    //商品保存之后
                    $this->afterSaving();

                    return ['status' => 1, 'good_id' => $this->goods_model->id];
                } else {
                    return ['status' => -1,'msg'=> '保存失败'];
                }
            }
        }

        return ['status' => -1,'msg'=>'商品保存失败'];
    }

    public function oldcreate()
    {
        $goods_data = $this->request->goods;


        $this->params = new GoodsParam();
        $this->goods_model = $this->getGoodsModel();

        $this->brands = Brand::getBrands()->getQuery()->select(['id','name'])->get();

        if ($goods_data) {
            //正则匹配富文本更改图片标签
            if ($goods_data['content']) {
                $goods_data['content'] = changeUmImgPath($goods_data['content']);
            }

            // 正则匹配富文本更改视频标签样式
            //$goods_data['content'] = preg_replace(htmlspecialchars('/<p[^>]*/'), htmlspecialchars('<p style="display: inline-block;"'), $goods_data['content']);
            $goods_data['content'] = preg_replace('/class="[^=]*/', 'class="edui-upload-video" controls', htmlspecialchars_decode($goods_data['content']));

            preg_match('/<video[^>]*/',  $goods_data['content'], $matches);

//            $matches[0] .= ' x5-playsinline="true" webkit-playsinline="true" playsinline="true"';
//
//            $goods_data['content'] = preg_replace('/<video[^>]*/', $matches[0], $goods_data['content']);

            $video_matche= '<video x5-playsinline="true" webkit-playsinline="true" playsinline="true" ';

            $goods_data['content'] = str_replace('<video', $video_matche, $goods_data['content']);
            $goods_data['content'] = htmlspecialchars($goods_data['content']);

            if ($this->type == 1) {
                $goods_data['status'] = 0;
            }

//            //商品视频地址
//            $goods_data['goods_video'] = yz_tomedia($goods_data['goods_video']);

            if (isset($goods_data['thumb_url'])) {
                $goods_data['thumb_url'] = serialize($goods_data['thumb_url']);
            }
            
            if (!$goods_data['virtual_sales']) {
                $goods_data['virtual_sales'] = 0;
            }

            if (!empty($this->request->widgets['sale']['max_point_deduct'])
                && !empty($goods_data['price'])
                && $this->request->widgets['sale']['max_point_deduct'] > $goods_data['price']) {
                return ['status' => -1, 'msg' => '积分最大抵扣金额大于商品现价'];
            }
            if (!empty($this->request->widgets['sale']['min_point_deduct'])
                && !empty($goods_data['price'])
                && $this->request->widgets['sale']['min_point_deduct'] > $goods_data['price']) {
                return ['status' => -1, 'msg' => '积分最少抵扣金额大于商品现价'];
            }
            if(empty($goods_data['price'])){
                $goods_data['price'] = 0;
            }
            if(empty($goods_data['cost_price'])){
                $goods_data['cost_price'] = 0;
            }
            if (mb_strlen($this->request['widgets']['advertising']['copywriting']) > 100) {
                return ['status' => -1, 'msg' => '广告宣传语文案输入超过100，请重新输入'];
            }
            $this->goods_model->fill($goods_data);
            $this->goods_model->widgets = $this->request->widgets;
            $this->goods_model->uniacid = \YunShop::app()->uniacid;
            $this->goods_model->weight = $this->goods_model->weight ? $this->goods_model->weight : 0;
            $validator = $this->goods_model->validator($this->goods_model->getAttributes());
            if ($validator->fails()) {
                $this->error = $validator->messages();
            } else {
                if ($this->goods_model->save()) {
                    (new \app\common\services\operation\GoodsLog($this->goods_model, 'create'));
                    GoodsService::saveGoodsMultiCategory($this->goods_model, $this->request->category, Setting::get('shop.category'));
                    GoodsParam::saveParam($this->request, $this->goods_model->id, \YunShop::app()->uniacid);
                    GoodsSpec::saveSpec($this->request, $this->goods_model->id, \YunShop::app()->uniacid);
                    GoodsOption::saveOption($this->request, $this->goods_model->id, GoodsSpec::$spec_items, \YunShop::app()->uniacid);
                    return ['status' => 1];
                } else {
                    return ['status' => -1];
                }
            }
        }
        $this->catetory_menus = CategoryService::getCategoryMultiMenu(['catlevel' => Setting::get('shop.category')['cat_level']]);
    }

    //商品模型保存后
    public function afterSaving()
    {
        event(new GoodsCreateEvent($this->goods_model));
    }

    public function setAfterHandle()
    {

    }


    public function setGoodsModel($model)
    {
        $this->goods_model = $model;
    }

    protected function getGoodsModel()
    {
        if (isset($this->goods_model)) {
            return $this->goods_model;
        }

        return new Goods();
    }


    protected function upload_search_img($goods_model)
    {



        $images = [
            [
                'goods_id' => $goods_model->id,
                'url' => yz_tomedia($goods_model->thumb)
            ]
        ];

        if (isset($goods_model->thumb_url)) {
            $thumb_imgs = unserialize($goods_model->thumb_url);
            foreach ($thumb_imgs as $v) {
                // 追加到 $images 数组
                array_push($images, [
                    'goods_id' => $goods_model->id,
                    'url' => yz_tomedia($v)
                ]);
            }
        }
        $option = request()->widgets['option']['option'];
        $has_option = request()->widgets['option']['has_option'];
        if($has_option){
            if($option){
                foreach ($option as $key=>$val){
                    if($val['thumb']){
                        array_push($images, [
                            'goods_id' => $goods_model->id,
                            'url' => yz_tomedia($val['thumb'])
                        ]);
                    }

                }
            }
        }


        // 将HTML实体转为普通字符
        /*$html_decoded = htmlspecialchars_decode($goods_model->content);

        // 使用正则表达式提取img标签的src属性
        preg_match_all('/<img[^>]+src="([^">]+)"/', $html_decoded, $matches);

        // 获取所有的图片链接
        $image_urls = $matches[1];

        if($image_urls){
            foreach ($image_urls as $v){
                array_push($images, [
                    'goods_id' => $goods_model->id,
                    'url' => yz_tomedia($v)
                ]);
            }
        }*/

        $apiUrl = 'https://search2.abangmi.com/myapp/index/search/batch_upload_images';
        $host  = Request()->getHost();
        if (strpos($host, 'test') !== false) {
            $is_type = 2;
        } else {
            $is_type = 1;
        }
        // 创建 POST 数据，params 为包含多个图片信息的数组
        $postData = [
            'params' => json_encode($images),  // 将数组转换为 JSON
            'type'=>$is_type,
            'goods_id'=>$goods_model->id
        ];

        // 初始化 cURL
        $ch = curl_init($apiUrl);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if ($response === false) {
            \Log::debug("cURL error: " . curl_error($ch));
        } else {
            \Log::debug("Response from API: " . $response);
        }

        curl_close($ch);
    }
}