<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2021/12/10
 * Time: 14:04
 */

namespace app\common\models\goods;

use app\common\models\BaseModel;


class GoodsOptionModelUrl extends BaseModel
{
    protected $table = 'yz_goods_option_model_url';

    public $timestamps = true;

    protected $fillable = [
         "option_id",
         "name",
         "model_url",
    ];

    protected $guarded = [''];


}