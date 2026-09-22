<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2021/9/14
 * Time: 17:38
 */

namespace app\backend\modules\goods\widget;


//cad模型
use app\backend\modules\goods\models\GoodsCad;


class CadWidget extends BaseGoodsWidget
{
    public $group = 'base';

    public $widget_key = 'cadmodel';

    public $code = 'cadmodel';

    public function pluginFileName()
    {
        return 'goods';
    }

    public function getData()
    {

        if (!is_null($this->goods)) {
            $goodsCad = GoodsCad::select('id','goods_id','3dmodel','cadfile')->where('goods_id', $this->goods->id)->first();

        }

        return [
            'goods_cad'=> $goodsCad,
        ];
    }


    public function pagePath()
    {
        return $this->getPath('resources/views/goods/assets/js/components/');
    }

    public function getLangData()
    {
        return [
            'option' => __('goods/cadmodel'),
        ];
    }
}
