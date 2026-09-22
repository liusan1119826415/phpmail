<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/5/17
 * Time: 16:07
 */

namespace app\common\events\cart;

use app\common\events\Event;
use app\common\models\MemberCart;
use app\framework\Http\Request;

class PreGoodsVerifyEvent extends Event
{
    protected $memberCart;

    protected $request;

    public function __construct($memberCart,$request)
    {
        $this->memberCart = $memberCart;

        $this->request = $request;
    }
    /**
     * (监听者)获取购物车model
     * @return MemberCart
     */
    public function getCartModel() {

        return $this->memberCart;
    }

    /**
     * 获取request对象
     * @return Request
     */
    public function getRequest()
    {
        if (!isset($this->request)) {
            $this->request = request();
        }
        return $this->request;
    }

    public function getDeliveryAddress()
    {
        return $this->memberCart->deliveryAddress;
    }
}