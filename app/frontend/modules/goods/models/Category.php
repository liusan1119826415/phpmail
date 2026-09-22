<?php

/**
 * Created by PhpStorm.
 * Author:  
 * Date: 2017/3/3
 * Time: 下午2:35
 */
namespace app\frontend\modules\goods\models;

use app\framework\Database\Eloquent\Builder;

/**
 * Class Category
 * @package app\frontend\modules\goods\models
 * @method static self recommendQuery()
 */
class Category extends \app\common\models\Category
{

    /**
     * @param Builder $model
     * @return Builder
     */
    public function scopeRecommendQuery(Builder $model)
    {
        $model->select('id','name','parent_id','level', 'thumb','adv_img','adv_url','small_adv_url');

        $model->where('is_home',1)->where('enabled',1);

        $model->orderBy('display_order', 'desc');

        return $model;
    }

    /**
     * @param array $search
     * @return self
     */
    public static function selectQuery($search = [])
    {
        $model = self::uniacid();

        $model->select('id','name','parent_id','level', 'thumb','adv_img','adv_url','small_adv_url','plugin_id')
            ->where('enabled',1);

        if (isset($search['parent_id']) && is_numeric($search['parent_id'])) {
            $model->where('parent_id', $search['parent_id']);
        }

        if (isset($search['plugin_id']) && is_numeric($search['plugin_id'])) {
            $model->where('plugin_id', $search['plugin_id']);
        }

        $model->orderBy('display_order', 'desc');

        return $model;
    }
}