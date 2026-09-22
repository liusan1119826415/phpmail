<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2021/7/28
 * Time: 9:10
 */

namespace app\frontend\modules\order\services;

use app\common\models\Order;
use app\frontend\modules\order\operations\OrderOperationInterface;

abstract class OrderFrontendButtonBase
{
    /**
     * @var Order
     */
    protected $order;

    public function init(Order $order)
    {
        $this->order = $order;
    }

    abstract function enable();

    abstract function getButton();

    protected function getStatus()
    {
        $method = $this->__getStatus($this->order->status);
        if (empty($method) || !method_exists($this,$method)) {
            return [];
        }
        return $this->$method();
    }

    protected function __getStatus($status)
    {
        $arr = [
            -1  => 'close',
            0  => 'waitPay',
            1  => 'waitSend',
            2  => 'waitReceive',
            3  => 'complete',
            4  => 'confirm',

        ];
        if($status == 1 && $this->order->orderStatus == 1){
            return "product";
        }elseif ($status == 1 && $this->order->orderStatus == 2){
            return "tailAmount";
        }elseif ($status == 1 && $this->order->orderStatus == 3){
            return "waitSend";
        }elseif($this->order->order_type == 5 && $status == 4){
            return 'waitDan';
        }/*elseif($this->order->order_type == 5 && $status == 2){
            return 'waitCheck';
        }*/elseif($this->order->order_type == 5 && $status == 0){
            return 'waitPay2';
        }elseif($this->order->order_type == 5 && $status == 3){
            return 'completeV2';
        }elseif($this->order->order_type == 5 && $status == -1){
            return 'closeV2';
        }/*elseif($this->order->order_type == 2 && $status == 0){
            return 'waitPayV3';
        }elseif($this->order->order_type == 2 && $status == 1){
            return 'waitSendV3';
        }elseif($this->order->order_type == 2 && $status == 2){
            return 'waitReceiveV3';
        }*/

        return $arr[$status];
    }


    protected function completeV2()
    {
        $arr = \app\common\modules\shop\OrderFrontendButtonConfig::current()->get('member_order_operations.completeV2');
        return $this->replaceButton($arr,'completeV2');
    }


    protected function waitDan()
    {
        $arr = \app\common\modules\shop\OrderFrontendButtonConfig::current()->get('member_order_operations.waitDan');
        return $this->replaceButton($arr,'waitDan');
    }

    protected function waitCheck()
    {
        $arr = \app\common\modules\shop\OrderFrontendButtonConfig::current()->get('member_order_operations.waitCheck');
        return $this->replaceButton($arr,'waitCheck');
    }

    protected function waitPay2()
    {
        $arr = \app\common\modules\shop\OrderFrontendButtonConfig::current()->get('member_order_operations.waitPay2');
        return $this->replaceButton($arr,'waitPay2');
    }


    protected function waitPay()
    {
        $arr = \app\common\modules\shop\OrderFrontendButtonConfig::current()->get('member_order_operations.waitPay');
        return $this->replaceButton($arr,'waitPay');
    }

    protected function waitSend()
    {
        $arr = \app\common\modules\shop\OrderFrontendButtonConfig::current()->get('member_order_operations.waitSend');
        return $this->replaceButton($arr,'waitSend');
    }

    protected function waitReceive()
    {
        $arr = \app\common\modules\shop\OrderFrontendButtonConfig::current()->get('member_order_operations.waitReceive');
        return $this->replaceButton($arr,'waitReceive');
    }

    protected function waitReceive3()
    {
        $arr = \app\common\modules\shop\OrderFrontendButtonConfig::current()->get('member_order_operations.waitReceive3');
        return $this->replaceButton($arr,'waitReceive');
    }

    protected function complete()
    {
        $arr = \app\common\modules\shop\OrderFrontendButtonConfig::current()->get('member_order_operations.complete');
        return $this->replaceButton($arr,'complete');
    }


    protected function closeV2()
    {
        $arr = \app\common\modules\shop\OrderFrontendButtonConfig::current()->get('member_order_operations.closeV2');
        return $this->replaceButton($arr,'closeV2');
    }

    protected function close()
    {
        $arr = \app\common\modules\shop\OrderFrontendButtonConfig::current()->get('member_order_operations.close');
        return $this->replaceButton($arr,'close');
    }

    protected function confirm()
    {
        $arr = \app\common\modules\shop\OrderFrontendButtonConfig::current()->get('member_order_operations.confirm');
        return $this->replaceButton($arr,'confirm');
    }

    protected function product()
    {
        $arr = \app\common\modules\shop\OrderFrontendButtonConfig::current()->get('member_order_operations.product');
        return $this->replaceButton($arr,'product');
    }

    protected function tailAmount()
    {
        $arr = \app\common\modules\shop\OrderFrontendButtonConfig::current()->get('member_order_operations.tailAmount');
        return $this->replaceButton($arr,'tailAmount');
    }

    /**
     * 执行按钮替换
     * @param $button
     * @param $key
     * @return array
     */
    protected function replaceButton($button,$key)
    {
        $replace = \app\common\modules\shop\OrderFrontendButtonConfig::current()->get('replace_order_frontend_button.'.$key);

        foreach ($replace as $value) {
            /**
             * @var OrderOperationInterface $operation
             */
            if (!class_exists($value['replace'])) {
                continue;
            }
            $operation = new $value['replace']($this->order);
            if (method_exists($operation,'isReplace') && $operation->isReplace()) {
                //替换验证通过
                $key = array_search($value['search'],$button);
                if ($key === false) {//未找到，直接加入
                    $button[] = $value['replace'];
                } else {
                    $button[$key] = $value['replace'];
                }
            }
        }
        return $button;
    }
}