<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/8/9
 * Time: 14:38
 */

namespace app\common\modules\refund\optional;


use app\common\models\Order;
use app\common\models\refund\RefundApply;

class RefundOnly extends OptionalTypesAbstract
{
    public function enable()
    {

        if(in_array($this->getOrder()->order_type,[1,2,3,4])){
            return $this->getOrder()->status >= Order::WAIT_SEND;
        }elseif($this->getOrder()->order_type == 5){
            if($this->getOrder()->status == Order::WAIT_PAY || $this->getOrder()->status == Order::COMPLETE){
                return true;
            }
        }

    }

    public function getName()
    {
        //return '退款(仅退款不退货)';
        if(in_array($this->getOrder()->order_type,[1,2,3,4])){
            return '退款';
        }elseif($this->getOrder()->order_type == 5){
            if($this->getOrder()->status == Order::WAIT_PAY){
                return "取消订单";
            }elseif($this->getOrder()->status == Order::COMPLETE){
                return '退款';
            }
        }

    }

    public function getValue()
    {

        if(in_array($this->getOrder()->order_type,[1,2,3,4])){
            return  RefundApply::REFUND_TYPE_REFUND_MONEY;
        }elseif($this->getOrder()->order_type == 5){
            if($this->getOrder()->status == Order::WAIT_PAY){
                return 1; //取消订单
            }elseif($this->getOrder()->status == Order::COMPLETE){
                return  RefundApply::REFUND_TYPE_REFUND_MONEY;
            }
        }

    }

    public function getIcon()
    {
        return 'icon-fontclass-daizhifu';
    }

    public function getDesc()
    {
        return '未收到货或者不用退货只退款';
    }

    public function notReceived()
    {
        return [
            '拍错/多拍/不想要',
            '货物破损',
            '快递送货问题',
            '差价',
            '其他原因'
        ];
    }

    public function received()
    {
        return [
            '货物破损',
            '少件、漏发',
            '差价',
            '商品质量问题',
            '其他原因',
        ];
    }

    public function getStatusData()
    {
        if($this->getOrder()->order_type == 5 && $this->getOrder()->status == Order::WAIT_PAY){
            return [
              [
                  "id"=>1,
                  "name"=>"专员未到"
              ],
                [
                    "id"=>2,
                    "name"=>"专员已到"
                ]
            ];
        }elseif($this->getOrder()->order_type == 2){
            return [
                [
                    "id"=>1,
                    "name"=>"未收到"
                ],
                [
                    "id"=>2,
                    "name"=>"已收到"
                ]
            ];
        }

        return [];
    }

}