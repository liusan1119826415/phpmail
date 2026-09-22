<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/8/9
 * Time: 14:46
 */

namespace app\common\modules\refund\optional;


interface OptionalTypesInterface
{
    public function enable();

    public function getName();

    public function getValue();

    public function getIcon();

    public function getDesc();

    public function notReceived();

    public function received();

    public function getStatusData();
}