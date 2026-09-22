<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/9/12
 * Time: 9:21
 */

namespace app\common\models\goods;

use app\common\models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsVideo extends BaseModel
{
    use SoftDeletes;

    public $table = 'yz_goods_video';
    protected $fillable = [
        'goods_id', // 添加此行
        'goods_video',
        'video_image'
    ];
    public function scopeOfGoodsId($query, $goodsId)
    {
        return $query->where('goods_id', $goodsId);
    }
}