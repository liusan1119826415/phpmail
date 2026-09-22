<?php
/**
 * Created by PhpStorm.
 * Author:  
 * Date: 2017/3/6
 * Time: 上午11:32
 */

namespace app\backend\widgets\goods;

use app\common\components\Widget;
use app\backend\modules\goods\models\Sale;
use app\common\facades\Setting;
use app\common\models\Area;

class SaleWidget extends Widget
{

    public function run()
    {
        $set = Setting::get('shop');
        $lang = \Setting::get('shop.lang', ['lang' => 'zh_cn']);
        $set['credit'] = $lang['member_center']['credit'] ?: '余额';
        $set['credit1'] = $lang['member_center']['credit1'] ?: '积分';
        $saleModel = new Sale();
        $parents = Area::getProvinces(0);

        $sale = Sale::getList($this->goods_id);

        if ($sale) {
            $saleModel->setRawAttributes($sale->toArray());
        }
        return view('goods.widgets.sale', [
            'item' => $saleModel,
            'parents' => $parents->toArray(),
            'set' => $set,
        ])->render();
    }
}

