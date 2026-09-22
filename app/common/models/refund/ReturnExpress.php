<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/3/23
 * Time: 上午10:49
 */

namespace app\common\models\refund;

use app\common\models\BaseModel;

/**
 * Class ReturnExpress
 * @property string address
 * @property array pack_goods
 * @property array contacts_info
 * @package app\common\models\refund
 */
class ReturnExpress extends BaseModel
{
    protected $fillable = [];
    protected $guarded = ['id'];
    public $table = 'yz_return_express';

    protected $attributes = [
        'pack_goods' => [],
        'contacts_info' => [],
    ];

    protected $casts = [
        'pack_goods' => 'json',
        'contacts_info' => 'json',
    ];

}