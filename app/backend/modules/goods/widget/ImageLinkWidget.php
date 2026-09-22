<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2021/9/14
 * Time: 10:13
 */

namespace app\backend\modules\goods\widget;


use app\common\models\goods\ImageLink;

class ImageLinkWidget extends BaseGoodsWidget
{
    public $group = 'tool';

    public $widget_key = 'image_link';

    public $code = 'imageLink';

    public function pluginFileName()
    {
        return 'goods';
    }

    public function getData()
    {

        $model = ImageLink::uniacid()->where('goods_id', $this->goods->id)->first();
        return [
            'plugin_goods' => [
                'status' => $model->status ? 1 : 0,
                'image_web_link' => trim($model->image_web_link) ?: '',
                'image_mini_link' => trim($model->image_mini_link) ?: '',
                'button_name' => trim($model->button_name) ?: '',
            ]
        ];
    }


    public function pagePath()
    {
        return $this->getPath('resources/views/goods/assets/js/components/');
    }


    public function usable()
    {
        return true;
    }
}