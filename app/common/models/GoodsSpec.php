<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/3/1
 * Time: 09:41
 */

namespace app\common\models;

use app\common\traits\HasLanguage;

/**
 * Class GoodsSpec
 * @package app\common\models
 * @property int uniacid
 * @property int goods_id
 */
class GoodsSpec extends \app\common\models\BaseModel
{
    use HasLanguage;

    public $table = 'yz_goods_spec';

    public $guarded = [];

    //public $timestamps = false;

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    public function hasManySpecsItem()
    {
        return $this->hasMany('app\common\models\GoodsSpecItem','specid','id');
    }
}
