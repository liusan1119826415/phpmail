<?php
/**
 * Author:
 * Date: 2017/7/13
 * Time: 下午3:10
 */

namespace app\common\models\goods;


use app\common\models\BaseModel;

class ProductTemplate extends BaseModel
{
    public $table = 'yz_product_template';

    public $fillable = ["goods_id","template_dwg"];



}