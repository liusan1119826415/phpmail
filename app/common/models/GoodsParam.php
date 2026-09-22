<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/2/23
 * Time: 08:43
 */

namespace app\common\models;


use app\common\traits\HasLanguage;

class GoodsParam extends \app\common\models\BaseModel
{
    use HasLanguage;

    public $table = 'yz_goods_param';

    public $guarded = [];

    public $timestamps = false;

    //public $timestamps = false;
}
