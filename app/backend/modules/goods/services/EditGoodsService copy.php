<?php

/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/5/2
 * Time: 上午11:51
 */

namespace app\backend\modules\goods\services;

use app\backend\modules\goods\models\Goods;
use app\backend\modules\goods\models\GoodsSpecItem;
use app\backend\modules\goods\models\GoodsParam;
use app\backend\modules\goods\models\GoodsSpec;
use app\backend\modules\goods\models\GoodsOption;
use app\backend\modules\goods\models\Brand;
use app\backend\modules\goods\models\GoodsStyleRelations;
use app\backend\modules\goods\models\GoodsVideo;
use app\common\events\goods\GoodsChangeEvent;
use app\common\exceptions\AppException;
use app\common\exceptions\ShopException;
use app\common\models\goods\GoodsImages;
use app\common\models\goods\GoodsRelation;
use app\common\models\GoodsCategory;
use app\common\requests\Request;
use app\common\services\Session;
use app\Jobs\DispatchesJobs;
use app\Jobs\GeneratePdfJob;
use app\Jobs\GoodsImageJob;
use app\Jobs\PdfJob;
use app\Jobs\ProductCadJob;
use Setting;
use Illuminate\Support\Facades\Redis;

class EditGoodsService
{
    public $goods_id;
    public $goods_model;
    public $request;
    public $catetory_menus;
    public $brands;
    public $optionsHtml;
    public $type;

    public function __construct($goods_id, $request, $type = 0)
    {
        $this->type = $type;
        $this->goods_id = $goods_id;
        $this->request = $request;
        $this->goods_model = Goods::with([
            'hasOneGoodsVideo',
            'hasManyGoodsCategory',
            'hasManyParams' => function ($query) {
                $query->orderBy('displayorder', 'asc');
            },
            'hasManySpecs'  => function ($query) {
                $query->orderBy('display_order', 'asc');
            }
        ])->find($goods_id);
        if (!$this->goods_model) {
            throw new AppException("商品信息未找到或已删除");
        }
    }

    public function edit()
    {


        //获取规格名及规格项
        $goods_data = $this->request->goods;

        if ($goods_data) {


            // 尝试获取编辑锁
            if (!LockGoodsService::acquireGoodsEditLock($this->goods_model->id)) {
                return ['status' => -1, 'msg' => '商品id' . $this->goods_model->id . '正在编辑中，请稍后再试'];

                //正则匹配富文本更改图片标签
                if ($goods_data['content']) {
                    $goods_data['content'] = changeUmImgPath($goods_data['content']);
                }
                preg_match('/<video[^>]*/', $goods_data['content'], $matches);

                $video_matche = '<video x5-playsinline="true" webkit-playsinline="true" playsinline="true" ';

                $goods_data['content'] = str_replace('<video', $video_matche, $goods_data['content']);

                $goods_data['content'] = htmlspecialchars($goods_data['content']);

                if ($this->type == 1) {
                    $goods_data['status'] = 0;
                }
                if (!$goods_data['virtual_sales']) {
                    $goods_data['virtual_sales'] = 0;
                }
                $goods_data['has_option'] = $this->request->widgets['option']['has_option'] ?: 0;

                $goods_data['weight'] = $goods_data['weight'] ? $goods_data['weight'] : 0;




                $goods_data['pdf_page'] = $goods_data['pdf_page'] ? serialize($goods_data['pdf_page']) : serialize([]);
                $goods_data['wiring_diagram'] = $goods_data['wiring_diagram'] ? serialize($goods_data['wiring_diagram']) : serialize([]);
                $goods_data['thumb_url'] = $goods_data['thumb_url'] ? serialize($goods_data['thumb_url']) : serialize([]);
                $goods_data['real_image'] = $goods_data['real_image'] ? serialize($goods_data['real_image']) : serialize([]);
                $goods_data['main_url'] = $goods_data['main_url'] ? serialize($goods_data['main_url']) : serialize([]);

                if ($goods_data['structure'] == "undefined" || $goods_data['structure'] == "") {
                    $goods_data['structure'] = "";
                }

                if ($goods_data['design'] == "undefined" || $goods_data['design'] == "") {
                    $goods_data['design'] = "";
                }
                // $related_goods_ids = $goods_data['related_goods_id'];
                // if($goods_data['related_goods_id']){
                //     $goods_data['related_goods_id'] = implode(",",$goods_data['related_goods_id']);
                // }else{
                //     $goods_data['related_goods_id'] = "";
                // }

                $newRelatedCollection = collect($goods_data['goods_relation']);
                if ($newRelatedCollection->isNotEmpty()) {
                    $goods_data['related_goods_id'] = implode(",", $newRelatedCollection->pluck('id')->toArray());
                } else {
                    $goods_data['related_goods_id'] = "";
                }

                if (
                    empty($this->request->widgets['sale']['point_deduct_type']) && !empty($this->request->widgets['sale']['max_point_deduct']) && !empty($goods_data['price'])
                    && $this->request->widgets['sale']['max_point_deduct'] > $goods_data['price']
                ) {
                    return ['status' => -1, 'msg' => '积分抵扣金额大于商品现价'];
                }



                $atlas = $goods_data['atlas'];

                $maintenance_doc = $goods_data['maintenance_doc'];

                // $install_guide = $goods_data['install_guide'];

                unset($goods_data['atlas']);

                unset($goods_data['maintenance_doc']);

                // unset($goods_data['install_guide']);

                $goods_data['price'] = $goods_data['price'] ?: 0;
                $goods_data['market_price'] = $goods_data['market_price'] ?: 0;
                $goods_data['cost_price'] = $goods_data['cost_price'] ?: 0;
                $goods_data['brand_id'] = $goods_data['brand_id'] ?: 0;
                //$goods_data['supp_id'] = Session::get('supplier')['id']?:0;

                $save_data = array_except(
                    $goods_data,
                    ['category', 'withhold_stock', 'video_image', 'goods_video', 'category_to_option', 'craft_materials', 'goods_style']
                );


                $this->goods_model->fill($save_data);
                $this->goods_model->widgets = $this->request->widgets;
                //其他字段赋值
                $this->goods_model->uniacid = \YunShop::app()->uniacid;
                //数据保存
                $validator = $this->goods_model->validator($this->goods_model->getAttributes());

                if ($validator->fails()) {
                    return ['status' => -1, 'msg' => $validator->messages()];
                    //$this->error($validator->messages());
                } else {

                    $this->goods_model->hasManyOptions;
                    $goods_model = clone $this->goods_model;
                    if ($this->goods_model->save()) {

                        //商品视频保存
                        GoodsVideo::store($this->goods_model->id, array_only($goods_data, ['video_image', 'goods_video']));

                        //商品图册保存、安装指南、保养手册

                        $job = new GoodsImageJob($atlas, $this->goods_model->id, \YunShop::app()->uniacid);
                        DispatchesJobs::dispatch($job, DispatchesJobs::LOW);

                        $job = new GeneratePdfJob($maintenance_doc, $this->goods_model->id, \YunShop::app()->uniacid, 'maintenance_doc');
                        DispatchesJobs::dispatch($job, DispatchesJobs::LOW);





                        //商品场景图等保存
                        //GoodsImages::saveImages($goods_data['goods_images'],$this->goods_model->id);
                        //商品工艺材质保存
                        GoodsStyleRelations::saveStyle($this->goods_model->id, $goods_data['craft_materials'], 2);
                        //商品风格保存
                        GoodsStyleRelations::saveStyle($this->goods_model->id, $goods_data['goods_style'], 1);
                        //商品分类保存

                        GoodsService::store(
                            $this->goods_model->id,
                            $goods_data['category'],
                            \Setting::get('shop.category')['cat_level'],
                            $goods_data['category_to_option']
                        );

                        //关联商品保存
                        //if($goods_data['goods_relation']){
                        GoodsRelation::relateToGoods($this->goods_model->id, $goods_data['goods_relation']);
                        //}


                        //商品属性保存
                        GoodsParam::store($this->goods_model->id, $this->request->widgets['param']);

                        //规格项和规格组合保存
                        SpecOptionService::store(
                            $this->goods_model->id,
                            $this->request->widgets['option'],
                            \YunShop::app()->uniacid
                        );

                        //$this->upload_search_img($this->goods_model);

                        event(new GoodsChangeEvent($goods_model)); //todo 有时需要监听商品更改过后规格的变化，挂件更改在规格保存之前，无法使用

                        // 注意：如果没有任何任务被创建，需要立即释放锁
                        $this->releaseGoodsEditLock($this->goods_model->id);
                        //显示信息并跳转
                        return ['status' => 1];
                    } else {
                        $this->releaseGoodsEditLock($this->goods_model->id);
                        return ['status' => -1, 'msg' => '商品保存失败'];
                    }
                }
            }
            
        }

        return ['status' => -1, 'msg' => '参数为空'];
    }

    protected function releaseGoodsEditLock($goodsId)
    {
        $counterKey = LockGoodsService::JOB_COUNTER_KEY_PREFIX . $goodsId;
        $currentCount = Redis::get($counterKey);
        if ($currentCount == 0) {
            LockGoodsService::releaseGoodsEditLock($goodsId);
        }
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
                    'url' => yz_tomedia($v['thumb'])
                ]);
            }
        }



        $option = request()->widgets['option']['option'];
        $has_option = request()->widgets['option']['has_option'];
        if ($has_option) {
            if ($option) {
                foreach ($option as $key => $val) {
                    if ($val['thumb']) {
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
        $host = Request()->getHost();
        if (strpos($host, 'test') !== false) {
            $is_type = 2;
        } else {
            $is_type = 1;
        }

        // 创建 POST 数据，params 为包含多个图片信息的数组
        $postData = [
            'params' => json_encode($images),  // 将数组转换为 JSON
            'type' => $is_type,
            'goods_id' => $goods_model->id
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

    public function oldedit()
    {
        //商品属性默认值
        $arrt_default = [
            'is_recommand' => 0,
            'is_new'       => 0,
            'is_hot'       => 0,
            'is_discount'  => 0
        ];

        //获取规格名及规格项
        $goods_data = $this->request->goods;

        $goods_data = array_merge($arrt_default, $goods_data);

        foreach ($this->goods_model->hasManySpecs as &$spec) {
            $spec['items'] = GoodsSpecItem::where('specid', $spec['id'])->orderBy('display_order', 'asc')->get()->toArray();
        }

        //获取具体规格内容html
        $this->optionsHtml = GoodsOptionService::getOptions($this->goods_id, $this->goods_model->hasManySpecs);

        //商品其它图片反序列化
        $this->goods_model->thumb_url = !empty($this->goods_model->thumb_url) ? unserialize(
            $this->goods_model->thumb_url
        ) : [];

        $this->goods_model->withhold_stock = $this->goods_model->withhold_stock;

        if ($goods_data) {
            //正则匹配富文本更改图片标签
            if ($goods_data['content']) {
                $goods_data['content'] = changeUmImgPath($goods_data['content']);
            }

            // 正则匹配富文本更改视频标签样式
            //$goods_data['content'] = preg_replace(htmlspecialchars('/<p[^>]*/'), htmlspecialchars('<p style="display: inline-block;"'), $goods_data['content']);
            $goods_data['content'] = preg_replace(
                '/class="[^=]*/',
                'class="edui-upload-video" controls',
                htmlspecialchars_decode($goods_data['content'])
            );

            preg_match('/<video[^>]*/', $goods_data['content'], $matches);

            //            $matches[0] .= ' x5-playsinline="true" webkit-playsinline="true" playsinline="true"';
            //
            //            $goods_data['content'] = preg_replace('/<video[^>]*/', $matches[0], $goods_data['content']);

            $video_matche = '<video x5-playsinline="true" webkit-playsinline="true" playsinline="true" ';

            $goods_data['content'] = str_replace('<video', $video_matche, $goods_data['content']);

            $goods_data['content'] = htmlspecialchars($goods_data['content']);

            if ($this->type == 1) {
                $goods_data['status'] = 0;
            }
            if (!$goods_data['virtual_sales']) {
                $goods_data['virtual_sales'] = 0;
            }
            $goods_data['has_option'] = $goods_data['has_option'] ? $goods_data['has_option'] : 0;
            $goods_data['weight'] = $goods_data['weight'] ? $goods_data['weight'] : 0;

            if (isset($goods_data['thumb_url'])) {
                $goods_data['thumb_url'] = serialize($goods_data['thumb_url']);
            } else {
                $goods_data['thumb_url'] = '';
            }


            $category_model = GoodsCategory::where("goods_id", $this->goods_model->id)->first();
            if (!empty($category_model)) {
                $category_model->delete();
            }
            GoodsService::saveGoodsMultiCategory(
                $this->goods_model,
                \YunShop::request()->category,
                Setting::get('shop.category')
            );

            if (
                !empty($this->request->widgets['sale']['max_point_deduct'])
                && !empty($goods_data['price'])
                && $this->request->widgets['sale']['max_point_deduct'] > $goods_data['price']
            ) {
                return ['status' => -1, 'msg' => '积分抵扣金额大于商品现价'];
            }
            if (mb_strlen($this->request['widgets']['advertising']['copywriting']) > 100) {
                return ['status' => -1, 'msg' => '广告宣传语文案输入超过100，请重新输入'];
            }
            $goods_data['price'] = $goods_data['price'] ?: 0;
            $goods_data['market_price'] = $goods_data['market_price'] ?: 0;
            $goods_data['cost_price'] = $goods_data['cost_price'] ?: 0;

            $this->goods_model->setRawAttributes($goods_data);
            $this->goods_model->widgets = $this->request->widgets;
            //其他字段赋值
            $this->goods_model->uniacid = \YunShop::app()->uniacid;
            $this->goods_model->id = $this->goods_id;
            //数据保存
            $validator = $this->goods_model->validator($this->goods_model->getAttributes());
            if ($validator->fails()) {
                return ['status' => -1, 'msg' => $validator->messages()];
                //$this->error($validator->messages());
            } else {
                if ($this->goods_model->save()) {
                    GoodsParam::saveParam($this->request, $this->goods_model->id, \YunShop::app()->uniacid);
                    GoodsSpec::saveSpec($this->request, $this->goods_model->id, \YunShop::app()->uniacid);
                    GoodsOption::saveOption(
                        $this->request,
                        $this->goods_model->id,
                        GoodsSpec::$spec_items,
                        \YunShop::app()->uniacid
                    );

                    event(new GoodsChangeEvent($this->goods_model)); //todo 有时需要监听商品更改过后规格的变化，挂件更改在规格保存之前，无法使用
                    //显示信息并跳转
                    return ['status' => 1];
                } else {
                    return ['status' => -1];
                }
            }
        }

        $this->brands = Brand::getBrand($this->goods_model->brand_id);
        if (isset($this->goods_model->hasManyGoodsCategory[0])) {
            foreach ($goods_categorys = $this->goods_model->hasManyGoodsCategory->toArray() as $goods_category) {
                $this->catetory_menus[] = CategoryService::getCategoryMultiMenu(
                    [
                        'catlevel' => Setting::get('shop.category')['cat_level'],
                        'ids'      => explode(",", $goods_category['category_ids'])
                    ]
                );
            }
        } else {
            $this->catetory_menus[] = CategoryService::getCategoryMultiMenu(
                ['catlevel' => Setting::get('shop.category')['cat_level'], 'ids' => []]
            );
        }
    }
}
