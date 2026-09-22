<?php
/**
 * Created by PhpStorm.
 * 
 *
 *
 * Date: 2021/12/28
 * Time: 11:43
 */

namespace app\backend\modules\goods\controllers;

use app\common\components\BaseController;
use app\backend\modules\goods\models\GoodsSetting;
use app\common\facades\Setting;

class GoodsSettingController extends BaseController
{
    public function index()
    {
        $data = request()->data;

        $config_data = GoodsSetting::getSet();

        $config_data = $config_data ? $config_data->toArray(): [];

        if ($data) {

            if ($config_data) {
                GoodsSetting::find($config_data['id'])->delete();
            }
            //todo 这里写什么每个数据都保存在不同地方，直接一个数组保存到 yz_setting 表里不就行了？？？
            $data['buy_button']=implode('|',$data['buy_button']);
            GoodsSetting::saveSet($data);
            Setting::set('goods.profit_show_status', $data['profit_show_status'] ? 1 : 0);
            Setting::set('goods.hide_goods_sales', $data['hide_goods_sales'] ? 1 : 0);
            Setting::set('goods.copy_real_sales', $data['copy_real_sales'] ? 1 : 0);
            Setting::set('goods.market_price_show', $data['market_price_show'] ? 1 : 0);

            Setting::set('shop.goods', $data);
            return $this->successJson('设置保存成功');
        }
        $config_data['profit_show_status'] = Setting::get('goods.profit_show_status') ? 1 : 0;
        $config_data['hide_goods_sales'] = Setting::get('goods.hide_goods_sales') ? 1 : 0;
        $config_data['buy_button']=$config_data['buy_button']?explode('|',$config_data['buy_button']):['cart','buy'];
        $config_data['copy_real_sales'] = Setting::get('goods.copy_real_sales');
        $config_data['copy_real_sales'] = !is_numeric($config_data['copy_real_sales']) ||  $config_data['copy_real_sales'] ? 1 : 0;
        $config_data['market_price_show'] =  Setting::get('goods.market_price_show') ? 1 : 0;


        //兼容分散的商品设置，为之后统一设置好修改
        $goods_set = Setting::get('shop.goods')?:[];
        //$goods_set['buy_button'] = $goods_set['buy_button']?explode('|',$goods_set['buy_button']):['cart','buy'];

        $config_data = array_merge($goods_set, $config_data);

        return view('goods.setting.index', [
            'set' => json_encode($config_data)
        ])->render();

    }
}