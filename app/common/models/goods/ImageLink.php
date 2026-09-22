<?php

namespace app\common\models\goods;

use app\common\models\BaseModel;
use app\common\models\Goods;
use Illuminate\Database\Eloquent\Model;

/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/2/22
 * Time: 下午5:54
 */
class ImageLink extends BaseModel
{
    public $table = 'yz_goods_image_link';
    public $timestamps = true;
    public $attributes = [];
    public $appends = ['image_web_link_url'];
    protected $guarded = [];

    public function getImageWebLinkUrl()
    {
        return $this->attributes['image_web_link'] ? yz_tomedia($this->attributes['image_web_link']) : '';
    }

    public function relationSave($goods_id, $data, $operate)
    {
        if (empty($data)) {
            return;
        }
        if ($operate == 'deleted') {
            self::uniacid()->where('goods_id', $goods_id)->delete();
            return;
        }


        $plugin_goods = $data['plugin_goods'];
        if (!$model = self::uniacid()->where('goods_id', $goods_id)->first()) {
            $model = new ImageLink([
                'uniacid' => \YunShop::app()->uniacid,
                'goods_id' => $goods_id,
            ]);
        }
        $model->status = $plugin_goods['status'] ? 1 : 0;
        $model->image_web_link = trim($plugin_goods['image_web_link']) ?: '';
        $model->image_mini_link = trim($plugin_goods['image_mini_link']) ?: '';
        $model->button_name = trim($plugin_goods['button_name']) ?: '';
        $model->save();
    }


}