<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2021/10/29
 * Time: 16:47
 */

namespace app\exports\generator;


class ShopOrderFromGenerator extends MergeFromGenerator
{
    public $second = ['goodsId','goodsTitle', 'goodsShort', 'goodsOption', 'goodsCode', 'goodsBarcode', 'goodsNum', 'refundNum', 'lastNum', 'memberPrice', 'goodsCostPrice', 'profit', 'diyForm', 'goodsSource', 'supplierName', 'storeName','goodsSku'];
    public $third = ['firstCategory', 'secondCategory', 'thirdCategory'];

}
