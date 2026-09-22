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


class GoodsOptionModel extends BaseModel
{
    protected $table = 'yz_goods_option_model';

    public $timestamps = true;

    protected $fillable = [
      "option_id",
      "name",
        "originName",
        "changeLock",
        "default_color",
        "select_color",
        "map_param",
        "meshs_name",
        "sort",
        "visible"
    ];

    protected $guarded = [''];


}