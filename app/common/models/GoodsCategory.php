<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/2/22
 * Time: 19:35
 */

namespace app\common\models;

use app\common\models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use app\backend\modules\goods\observers\GoodsCategoryObserver;

/**
 * Class GoodsCategory
 * @package app\common\models
 * @property string category_ids
 */
class GoodsCategory extends BaseModel
{
    public $table = 'yz_goods_category';

    public $guarded = ['updated_at', 'created_at', 'deleted_at'];

    public function getGoodsFirstCatName($goods_id)
    {
        $goods_categorys = self::where('goods_id', $goods_id)->get();
        if ($goods_categorys->isEmpty()) {
            return '';
        }
        $category_ids = [];
        $goods_categorys->each(function ($goods_category) use (&$category_ids) {
            $category_ids = array_merge($category_ids, $goods_category->category_ids ? explode(',', $goods_category->category_ids) : []);
        });
        if (!$category_ids) {
            return '';
        }
        $category = Category::uniacid()->where('level', 1)->where('enabled', 1)->whereIn('id', array_values(array_unique($category_ids)))->first();
        return $category ? $category->name : '';
    }

    public function goods()
    {
        return $this->hasOne('app\common\models\Goods','id','goods_id');
    }

    public function goodsDiscount()
    {
        return $this->hasMany(GoodsDiscount::class, 'goods_id', 'goods_id');
    }

    public function goodsOption()
    {
        return $this->hasOne('app\common\models\GoodsOption','id','goods_option_id');
    }

    public function delCategory($goods_id)
    {
        return $this->where(['goods_id' => $goods_id])
            ->delete();
    }

    public static function boot()
    {
        parent::boot();
        //注册观察者
        static::observe(new GoodsCategoryObserver);
    }

}