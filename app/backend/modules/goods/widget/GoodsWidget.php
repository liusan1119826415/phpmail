<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2021/9/9
 * Time: 15:38
 */

namespace app\backend\modules\goods\widget;

use app\backend\modules\goods\models\Brand;
use app\backend\modules\goods\models\Category;
use app\backend\modules\goods\models\Goods;
use app\backend\modules\goods\models\GoodsOption;
use app\backend\modules\goods\models\GoodsStyleRelations;
use app\platform\modules\system\models\SystemSetting;

class GoodsWidget extends BaseGoodsWidget
{
    public $group = 'base';

    public $widget_key = 'goods';

    public $code = 'goods';

    public function pluginFileName()
    {
        return 'goods';
    }

    public function getCategoryList()
    {
        return Category::getAllCategoryGroupArray();
    }

    public function categoryLevel()
    {
        return \Setting::get('shop.category')['cat_level']?:2;
    }

    public function getData()
    {
        $global_setting = SystemSetting::settingLoad('global', 'system_global');
        //商品属性默认值
        $goods = [
            'is_recommand' => 0,
            'is_new' => 0,
            'is_hot' => 0,
            'is_discount' => 0,
            'hide_goods_sales' => 0,
            'hide_goods_sales_alone' => 0,
        ];
        $result['category_level'] = $this->categoryLevel();
        $result['category_list'] = $this->getCategoryList();
        $result['brand'] = Brand::getBrands()->getQuery()->select(['id','name'])->get();
        $result['limit_file_size'] = $global_setting['audio_limit'];
        $result['goods'] = $goods;

        if (is_null($this->goods)) {
            $result['goods']['pdf_page'] = [];
            $result['goods']['hide_goods_sales_switch'] = in_array($this->request->route,['goods.goods.widget-column','plugin.auction.admin.auction.widget-column']) ? 1 : 0;
            return $result;
        }

        $goods = array_merge($goods, $this->goods->toArray());
        $goods = array_except($goods,['content','old_id','is_deleted','created_at','updated_at','deleted_at']);

        if ($this->goods->thumb) {
            $goods['thumb_link'] = yz_tomedia($this->goods->thumb);
        }

        //商品其它图片反序列化
        $goods['thumb_url'] = $this->goods->thumb_url?unserialize($this->goods->thumb_url) : [];

        //商品实拍图
        $goods['real_image'] = $this->goods->real_image?unserialize($this->goods->real_image) : [];

        //商品视频处理
        $goods['goods_video'] = $this->goods->hasOneGoodsVideo->goods_video?: '';
        $goods['video_image'] = $this->goods->hasOneGoodsVideo->video_image ?: '';
        $goods['goods_video_link'] = $this->goods->hasOneGoodsVideo->goods_video? yz_tomedia($this->goods->hasOneGoodsVideo->goods_video) : '';
        $goods['video_image_link'] = $this->goods->hasOneGoodsVideo->video_image? yz_tomedia($this->goods->hasOneGoodsVideo->video_image) : '';

        $goods['advert_pic_link'] = $this->goods->advert_pic? yz_tomedia($this->goods->advert_pic) : '';
        $goods['background_pic_link'] = $this->goods->background_pic? yz_tomedia($this->goods->background_pic) : '';

        $goods['withhold_stock'] = $this->goods->withhold_stock;
        
        //分类

        if (!$this->goods->hasManyGoodsCategory->isEmpty()){

            $category = [];
            $category_to_option = [];
            foreach($this->goods->hasManyGoodsCategory->toArray() as $goods_category){
                $category_ids  = explode(",", $goods_category['category_ids']);

                //商品分类层级大于设置层级
                if (count($category_ids) > $result['category_level']) {
                    array_pop($category_ids);
                }

                $category_item = Category::select('id','name','level')->uniacid()
                    ->whereIn('id', $category_ids)->orderBy('level')->get();

                if (!$category_item->isEmpty() && $category_item->count() > 1) {
                    $category[] = $category_item->toArray();
                    $category_to_option[] = ['goods_option_id'=>$goods_category['goods_option_id']?:''];
                }
            }
            $goods['category'] = $category;
            $goods['category_transforme'] = $this->transformed($category);
            $goods['category_to_option'] = $category_to_option;
        } else {
            $goods['category'] = [];
            $goods['category_to_option'] = [];
        }
        $relatedGoods = $this->goods->getRelatedGoods();

        if ($relatedGoods->isNotEmpty()) {
            $goods['related_goods_id'] = $relatedGoods->pluck('id')->toArray();
            $goods['related_goods_name'] = $relatedGoods->pluck('title')->toArray();

        } else {
            $goods['related_goods_id'] = [];
            $goods['related_goods_name'] = [];
            $goods['related_goods_details'] = [];
        }
        if($this->goods->pdf_page){
            $goods['pdf_page'] = unserialize($this->goods->pdf_page);
        }else{
            $goods['pdf_page'] = [];
        }


        $goods['wiring_diagram'] = $this->goods->wiring_diagram?unserialize($this->goods->wiring_diagram):[];
        $goods['main_url'] = $this->goods->main_url?unserialize($this->goods->main_url):[];

        //商品风格
        $goods['goods_style'] = GoodsStyleRelations::where("goods_id",$this->goods->id)->where('type',1)->pluck("style_id")->all();
        $goods['craft_materials'] = GoodsStyleRelations::where("goods_id",$this->goods->id)->where('type',2)->pluck("style_id")->all();


        $unserialized_data = @unserialize($this->goods->e_catalog_pdf);
        if ($unserialized_data !== false) {
            $goods['e_catalog_pdf_url'] = $unserialized_data;
        } else {
            // 如果反序列化失败，仍然返回原数据
            $goods['e_catalog_pdf_url'] = yz_tomedia($this->goods->e_catalog_pdf);
        }


        $unserialized_data = @unserialize($this->goods->maintenance_doc);

        if ($unserialized_data !== false) {
            $goods['maintenance_doc'] = $unserialized_data;
        } else {
            // 如果反序列化失败，仍然返回原数据
            $goods['maintenance_doc'] = yz_tomedia($this->goods->maintenance_doc);
        }


        $unserialized_data = @unserialize($this->goods->install_guide);
        if ($unserialized_data !== false) {
            $goods['install_guide'] = $unserialized_data;
        } else {
            // 如果反序列化失败，仍然返回原数据
            $goods['install_guide'] = yz_tomedia($this->goods->install_guide);
        }


        $atlas = json_decode($this->goods->atlas,true);

        if($atlas == false){
            $goods['atlas'] = ['data'=>[]];
        }else{
            $goods['atlas'] = $atlas;
        }

        $result['goods'] = $goods;

        $result['options'] = [];
        $result['category_to_option_open'] = \Setting::get('shop.category.category_to_option') ? : 0;

        if (!in_array($this->getRoute(),['shop','supplier'])) {
            $result['category_to_option_open'] = 0;
        }
        if (!is_null($this->goods) && $this->goods->has_option) {
            $result['options'] = GoodsOption::uniacid()
                ->select('id','title')
                ->where('goods_id',$this->goods->id)
                ->get()->toArray();
        }
        $result['goods']['hide_goods_sales_switch'] = in_array($this->goods->plugin_id, [0, 67]) ? 1 : 0;

        return $result;
    }


    private function safeUnserializeArray($value)
    {
        return !empty($value) ? array_map(fn($url) => yz_tomedia($url), @unserialize($value) ?: []) : [];
    }


    protected function transformed($category)
    {
        $cat_level = \Setting::get('shop.category')['cat_level'];
        if($cat_level == 2){
            $transformed = collect($category)
                ->flatten(1) // 将多维数组展平成一级
                ->groupBy('level') // 按照 level 分组
                ->map(function ($items, $level) {
                    if ($level == 1) {
                        return [
                            'id' => $items->first()['id'],
                            'name' => $items->first()['name'],
                            'level' => (int) $level,
                        ];
                    }

                    return [
                        'id' => $items->pluck('id')->toArray(),
                        'name' => $items->pluck('name')->toArray(),
                        'level' => (int) $level,
                    ];
                })
                ->values()
                ->toArray();
            $transformed = [$transformed];
        }elseif($cat_level == 3){

            $transformed = collect($category)
                ->groupBy(function ($group) {
                    return $group[0]['level']; // 按第一个元素的 level 进行分组
                })
                ->map(function ($groupedItems) {
                    $result = [];
                    foreach ($groupedItems as $items) {
                        foreach ($items as $item) {
                            $level = $item['level'];
                            if (!isset($result[$level])) {
                                $result[$level] = [
                                    'id' => $level == 1 || $level == 2 ? $item['id'] : [],
                                    'name' => $level == 1 || $level == 2 ? $item['name'] : [],
                                    'level' => $level,
                                ];
                            }
                            if ($level == 3) {
                                $result[$level]['id'][] = $item['id'];
                                $result[$level]['name'][] = $item['name'];
                            }
                        }
                    }

                    return array_values($result); // 返回按 level 排序的数组
                })
                ->flatten(1) // 转成单层数组
                ->groupBy('level') // 按 level 分组
                ->map(function ($group, $level) {
                    return $level == 3
                        ? [
                            'id' => collect($group)->pluck('id')->flatten()->unique()->values()->toArray(),
                            'name' => collect($group)->pluck('name')->flatten()->unique()->values()->toArray(),
                            'level' => (int) $level,
                        ]
                        : $group->first(); // 返回 level 1 或 2 的第一条记录
                })
                ->values() // 将数据转成值数组
                ->toArray();

            $transformed = [$transformed];

        }

        return $transformed;
    }


    /**
     * hide_status 隐藏状态
     * hide_weight 隐藏重量
     * hide_volume 隐藏体积
     * hide_product_sn 隐藏商品条码
     * appoint_type 指定商品类型
     * appoint_need_address 指定下单是否需要地址
     * appoint_need_address 指定下单是否需要地址
     * @return array
     */
    public function attrHide()
    {
        return [];
    }

    public function pagePath()
    {
        return  $this->getPath('resources/views/goods/assets/js/components/');
    }
    public function getLangData()
    {
        return [
            'goods' => __('goods/goods'),
        ];
    }
}