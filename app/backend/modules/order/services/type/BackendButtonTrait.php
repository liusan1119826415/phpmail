<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2021/2/23
 * Time: 11:32
 */

namespace app\backend\modules\order\services\type;


use app\backend\modules\charts\models\OrderGoods;
use app\common\services\Session;
use app\frontend\modules\order\operations\OrderOperationInterface;

trait BackendButtonTrait
{

    //订单操作
    protected function getButtonModels()
    {
        $operationsSettings = $this->getCurrentOperations();
        $operations = array_map(function ($operationName) {
            /**
             * @var OrderOperationInterface $operation
             */
            $operation = new $operationName($this->getOrder(), $this);
            if (!$operation->enable()) {
                return null;
            }
            $result['name'] = $operation->getName();
            $result['value'] = $operation->getValue();
            $result['api'] = $operation->getApi();
            $result['type'] = $operation->getType();

            return $result;
        }, $operationsSettings);

        $operations = array_filter($operations);
        return array_values($operations) ?: [];
    }

    //加载插件按钮到商店订单列表
    protected function getPluginOperations($status, $arr = [])
    {
        $plugin_arr = empty(\app\common\modules\shop\ShopConfig::current()->get('backend_order_list_plugin_button.' . $status)) ? [] : \app\common\modules\shop\ShopConfig::current()->get('backend_order_list_plugin_button.' . $status);
        $arr = array_merge($arr, $plugin_arr);
        if ($plugin_arr) {
            foreach ($plugin_arr as $v) {
                $class = new $v($this->getOrder(), $this);
                if (!method_exists($class, 'replace')) {
                    continue;
                }
                if (!$replace_class = $class->replace()) {
                    continue;
                }
                $key = array_search($replace_class, $arr);
                if ($key !== false) {
                    unset($arr[$key]);
                }
            }
        }
        return array_values($arr);
    }

    //根据订单状态获取当前操作按钮
    protected function getCurrentOperations()
    {
        $method_name = $this->getStatusMethod($this->getOrder()->status);
        if (!method_exists($this, $method_name)) {
            return [];
        }

        return $this->$method_name();
    }

    //0 待支付
    protected function waitPayOperations()
    {
        return $this->getPluginOperations(0, [
            \app\backend\modules\order\operations\Pay::class,

        ]);
    }


    //1 待发货
    protected function waitSendOperations()
    {
        return $this->getPluginOperations(1, [
            \app\backend\modules\order\operations\Send::class,
            // \app\backend\modules\order\operations\SeparateSend::class,
        ]);
    }


    //2 待收货
    protected function waitReceiveOperations()
    {
        return $this->getPluginOperations(2, [
            \app\backend\modules\order\operations\SeparateSend::class,
            \app\backend\modules\order\operations\Receive::class,
            \app\backend\modules\order\operations\UpdateSend::class,
            \app\backend\modules\order\operations\CancelSend::class,
        ]);
    }

    //3 已完成
    protected function completeOperations()
    {
        return $this->getPluginOperations(3, []);
    }

    //4 待确认
    protected function confirmOperations()
    {
        $arr = [
            \app\backend\modules\order\operations\Confirm::class,
        ];
        $orderGoods = OrderGoods::where('order_id',$this->getOrder()->id)->where('is_customized',1)->first();
        if ($orderGoods) {
            array_push($arr, \app\backend\modules\order\operations\ChangePrice::class);
        }
        return $this->getPluginOperations(4, $arr);
    }


    //投标订单待支付
    protected function waitBidPayOperations()
    {
        return $this->getPluginOperations(0, []);
    }

    //投标订单待发货
    protected function waitBidSendOperations()
    {
        return $this->getPluginOperations(1, [
            \app\backend\modules\order\operations\Send::class,
        ]);
    }

    //投标订单待验收
    protected function waitBidReceiveOperations()
    {
        return $this->getPluginOperations(2, [
            \app\backend\modules\order\operations\CancelSend::class,
        ]);
    }


    // -1 已关闭
    protected function closeOperations()
    {
        return $this->getPluginOperations(-1, []);
    }

    protected function uploadInstall()
    {
        return $this->getPluginOperations(4, [
            \app\backend\modules\order\operations\Confirm::class,
        ]);
    }

    protected function uploadInfo()
    {
        return $this->getPluginOperations(2, [
            \app\backend\modules\order\operations\Receive::class,
        ]);
    }

    protected function extraFee()
    {
        return $this->getPluginOperations(0, [
           // \app\backend\modules\order\operations\Pay::class,
        ]);
    }

    protected function notQuoted()
    {
        return $this->getPluginOperations(1, [
            \app\backend\modules\order\operations\UploadPrice::class,
            \app\backend\modules\order\operations\ViewPoint::class,
        ]);
    }

    protected function quoted()
    {
        return $this->getPluginOperations(1, [
            \app\backend\modules\order\operations\UploadPrice::class,
            \app\backend\modules\order\operations\ViewPoint::class,
        ]);
    }

    protected function comfirmSend()
    {
        return $this->getPluginOperations(1, [
            \app\backend\modules\order\operations\ConfirmSend::class,
            \app\backend\modules\order\operations\ViewPoint::class,
        ]);
    }

    protected function comfirmSign()
    {
        return $this->getPluginOperations(1, [
            \app\backend\modules\order\operations\ConfirmSign::class,
            \app\backend\modules\order\operations\ViewPoint::class,
            \app\backend\modules\order\operations\ViewCertificate::class,
        ]);
    }

    protected function comfirmFinish()
    {
        return $this->getPluginOperations(1, [
            \app\backend\modules\order\operations\ViewCertificate::class,
        ]);
    }

    //待付款预付款
    protected function waitAdvancePayment()
    {
        $orderGoods = OrderGoods::where('order_id',$this->getOrder()->id)->where('is_customized',1)->first();
        if ($orderGoods) {
            return $this->getPluginOperations(0, [
                \app\backend\modules\order\operations\ChangePrice::class,
            ]);
        }else{
            return $this->getPluginOperations(0, [
            ]);
        }

    }

    //待生产
    protected function waitProduct()
    {
        return $this->getPluginOperations(0, [
            \app\backend\modules\order\operations\ConfirmProduct::class,
        ]);
    }

    //待发货
    protected function waitSendGoods()
    {
        return $this->getPluginOperations(0, [
            \app\backend\modules\order\operations\Send::class,
            \app\backend\modules\order\operations\ViewDelivery::class,
        ]);
    }


    protected function publishOrder()
    {

        return $this->getPluginOperations(0, [
            \app\backend\modules\order\operations\PublishOrder::class,
            \app\backend\modules\order\operations\ViewPoint::class,

        ]);

    }

    protected function publishOrder1()
    {
        return $this->getPluginOperations(0, [

            \app\backend\modules\order\operations\ViewPoint::class,

        ]);
    }

    protected function publishOrder2()
    {
        return $this->getPluginOperations(0, [
            \app\backend\modules\order\operations\SelectLocistic::class,
            \app\backend\modules\order\operations\ViewPoint::class,

        ]);

    }

    protected function publishOrder3()
    {
        return $this->getPluginOperations(0, [

            \app\backend\modules\order\operations\ViewPoint::class,


        ]);

    }

    protected function publishOrder4()
    {
        return $this->getPluginOperations(0, [

            \app\backend\modules\order\operations\ViewPoint::class,
            \app\backend\modules\order\operations\ViewCertificate::class,

        ]);
    }

    protected function publishOrder5()
    {
        return $this->getPluginOperations(0, [

            \app\backend\modules\order\operations\ViewPoint::class,
            \app\backend\modules\order\operations\ViewCertificate::class,

        ]);

    }

    protected function publishOrder6()
    {
        return $this->getPluginOperations(0, [

            \app\backend\modules\order\operations\ViewPoint::class,

        ]);
    }


    protected function installOrder1()
    {
        return $this->getPluginOperations(0, [
            \app\backend\modules\order\operations\PublishOrder::class,
            \app\backend\modules\order\operations\ViewGoods::class,

        ]);
    }


    protected function installOrder2()
    {
        return $this->getPluginOperations(0, [
            \app\backend\modules\order\operations\ViewGoods::class,

        ]);
    }


    protected function installOrder3()
    {
        return $this->getPluginOperations(0, [
            \app\backend\modules\order\operations\SelectTeam::class,
            \app\backend\modules\order\operations\ViewGoods::class,

        ]);
    }


    protected function installOrder4()
    {
        return $this->getPluginOperations(0, [
            \app\backend\modules\order\operations\ViewGoods::class,

        ]);
    }

    protected function installOrder5()
    {
        return $this->getPluginOperations(0, [
            \app\backend\modules\order\operations\ViewGoods::class,

        ]);
    }

    protected function installOrder6()
    {
        return $this->getPluginOperations(0, [
            \app\backend\modules\order\operations\ViewGoods::class,
            \app\backend\modules\order\operations\ViewCertificate::class,

        ]);
    }


    protected function installOrder7()
    {
        return $this->getPluginOperations(0, [
            \app\backend\modules\order\operations\ResetPublishOrder::class,
            \app\backend\modules\order\operations\ViewGoods::class,

        ]);
    }

    protected function installOrderOne()
    {
        return $this->getPluginOperations(0, [
            \app\backend\modules\order\operations\ViewGoods::class,
            \app\backend\modules\order\operations\Logistic::class,

        ]);
    }

    protected function installOrderTwo()
    {
        return $this->getPluginOperations(0, [

            \app\backend\modules\order\operations\UploadCertificate::class,
            \app\backend\modules\order\operations\ViewGoods::class,

        ]);
    }


    protected function installOrderThree()
    {
        return $this->getPluginOperations(0, [


            \app\backend\modules\order\operations\ViewGoods::class,
            \app\backend\modules\order\operations\ViewCertificate::class,

        ]);
    }

    protected function installOrderClose()
    {
        return [];
    }






    /**
     * 根据状态返回方法
     * @param $status
     * @return mixed
     */
    protected function getStatusMethod($status)
    {

        $methodName = [
            /* 0 => 'waitPayOperations',
             1 => 'waitSendOperations',
             2 => 'waitReceiveOperations',
             3 => 'completeOperations',
             -1 => 'closeOperations',*/
            4 => 'confirmOperations'
        ];


        if ($this->getOrder()->parent_id == 0 && !Session::get('supplier')['id'] && request()->get("mtype") == 2) {

            $methodName = [
                1 => 'publishOrder',
                2 => 'publishOrder1',
                3 => 'publishOrder2',
                4 => 'publishOrder3',
                5 => 'publishOrder4',
                6 => 'publishOrder5',
                -1 => 'publishOrder6',

            ];
            return $methodName[$this->getOrder()->dispense_status];
        }


        if ($this->getOrder()->parent_id == 0 && !Session::get('supplier')['id'] && request()->get("mtype") == 1) {

            $methodName = [
                1 => 'installOrder1',
                2 => 'installOrder2',
                3 => 'installOrder3',
                4 => 'installOrder4',
                5 => 'installOrder5',
                6 => 'installOrder6',
                -1 => 'installOrder7',

            ];
            return $methodName[$this->getOrder()->install_dispense_status];
        }



        if ($this->getOrder()->parent_id == 0 && Session::get('supplier')['id'] && request()->get("mtype") == 6) {

            $methodName = [
                1 => 'installOrderOne',
                2 => 'installOrderTwo',
                3 => 'installOrderThree',
                -1 => 'installOrderClose',


            ];
            return $methodName[$this->getOrder()->installOrder->install_status];
        }




        if ($this->getOrder()->parent_id == 0  && Session::get('supplier')['id'] && request()->mtype == 3) {

            $methodName = [
                1 => 'notQuoted',
                2 => 'quoted',
                3 => 'notBid',
                4 => 'bided'
            ];

            return $methodName[$this->getOrder()->hasOneSupplierPrice->bidding_status];
        }

        if ($this->getOrder()->parent_id == 0 && Session::get('supplier')['id'] && request()->mtype == 5) {

            $methodName = [
                1 => 'notQuoted',
                2 => 'quoted',
                3 => 'notBid',
                4 => 'bided'
            ];
            return $methodName[$this->getOrder()->hasOneSupplierInstallPrice->bidding_status];
        }

        if ($this->getOrder()->parent_id == 0 && $this->getOrder()->logistics_status == 2 && request()->mtype == 4) {

            return "comfirmSend";
        }

        if ($this->getOrder()->parent_id == 0 && $this->getOrder()->logistics_status == 3 && request()->mtype == 4) {

            return "comfirmSign";
        }

        if ($this->getOrder()->parent_id == 0 && $this->getOrder()->logistics_status == 4 && request()->mtype == 4) {

            return "comfirmFinish";
        }


        if ($this->getOrder()->status == 0 && $this->getOrder()->order_type == 1) {
            //待付预付款
            return "waitAdvancePayment";
        } elseif ($this->getOrder()->status == 1 && $this->getOrder()->order_type == 1 && $this->getOrder()->orderStatus == 1) {
            //待生产
            return "waitProduct";
        } elseif ($this->getOrder()->status == 1 && $this->getOrder()->order_type == 1 && $this->getOrder()->orderStatus == 3) {
            //确认发货
            return "waitSendGoods";
        }
        if ($this->getOrder()->status == 1 && $this->getOrder()->orderStatus == 1) {
            $methodName[1] = "waitSendOperations";
        }
        if (in_array($this->getOrder()->order_type, [2, 3, 4])) {
            $methodName = [
                0 => 'waitBidPayOperations',
                1 => 'waitBidSendOperations',
                2 => 'waitBidReceiveOperations',
                3 => 'completeOperations',
                -1 => 'closeOperations',
            ];
        }

        if (in_array($this->getOrder()->order_type, [5])) {
            $methodName = [
                4 => 'uploadInstall',
                0 => 'uploadInfo',

            ];
        }

        return $methodName[$status];
    }
}