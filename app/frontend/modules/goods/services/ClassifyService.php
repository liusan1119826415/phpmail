<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/1/11
 * Time: 15:33
 */

namespace app\frontend\modules\goods\services;


use app\framework\Http\Request;

class ClassifyService
{
    public $shopSet;

    public function __construct()
    {

    }


    public function unifiedFormat(Request $request)
    {
        $plugin = $request->input('plugin', '');

        //供应商和咖啡机返回默认参数
        if ($plugin == 'supplier' || $plugin == 'coffee') {
            return $this->defaultTemplate();
        }

        return $this->decorateSet();
    }

    public function decorateSet()
    {
        //开启装修
        $decorateViewSet = \app\frontend\modules\home\services\ShopPublicDataService::getInstance()->getViewSet();
        if ($decorateViewSet['category']) {
            $category = $decorateViewSet['category'];
            return $this->unifyTemplate($category['code'], request()->input('type'));
        }


        return $this->defaultTemplate();

    }

    //这里是为了兼容装修模板，之后装修模板哪里要修改成开关
    protected function unifyTemplate($code,$port)
    {
        $template = $this->defaultTemplate();
        $template['imgw'] = 90;
        switch ($code) {
            case 'category01':
                //默认模板
                break;
            case 'category02':
                $template['classify_style'] = 2;
                $template['inner_click'] = 2;
                break;
            case 'category03':

                $template['classify_style'] = 2;
                $template['inner_click'] = 3;
                break;
            case 'category04':
                $template['goods_sort'] = 1;
                $template['cart_style'] = 0;
                //这里兼容
                $category_template_four_set=\Setting::get('decorate.category_four');
                if($category_template_four_set&&!app('plugins')->isEnabled('tiktok-cps')){
                    $category_template_four_set[1]['is_show']=false;
                }
                $template['tab']= $category_template_four_set?:[['key'=>'shop','is_show'=>true,'sort'=>1],['key'=>'tiktok','is_show'=>false,'sort'=>2]];
                break;
            case 'category05':
                $template['classify_position'] = 2;
                $template['cart_style'] = 2;
                break;
            case 'category06':
                $template['imgw'] = 120;
                break;
            default:
        }

        //分类样式二不需要显示商品列表
        if ($template['classify_style'] == 2) {
            $template['goods_sort'] = 0;
            $template['cart_style'] = 0;
        }

        return $template;
    }


    public function defaultTemplate()
    {

        /**
         * tab 分类页选项卡
         * classify_position 二级分类显示位置：1=右侧；2=和一级分类显示左侧
         * classify_style  分类样式：1=左侧分类名称+右侧商品列表；2=左侧分类名称+右侧二级分类列表
         * inner_click 点击跳转：1=商品详情；2=搜索页；3=旧三级分类页
         * goods_sort  商品排列方式：0=不显示；1=竖排两列；2=横排
         * cart_style  购物车：0=不显示；1=加入购物车；2=样式只能加入
         *
         */
        return [
            'tab' => [],
            'classify_position' => 1,
            'classify_style' => 1,
            'goods_sort' => 2,
            'inner_click' => 1,
            'cart_style' => 1,
        ];
    }
}