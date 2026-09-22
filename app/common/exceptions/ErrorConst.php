<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/11/9
 * Time: 10:11
 */

namespace app\common\exceptions;


class ErrorConst
{
    const ORDER_PAY_NOTICE =  'order_pay_notice';

    //订单已支付
    const ORDER_REPEAT_PAY = 1001;
    //订单已关闭
    const ORDER_CLOSE = 1004;
}