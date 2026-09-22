<?php
/**
 * Created by PhpStorm.
 * User: blank
 * Date: 2022/11/3
 * Time: 15:43
 */

namespace app\common\modules\refund;


use app\common\models\Order;
use app\common\models\refund\RefundApply;
use app\common\modules\refund\product\RefundOrderTypeBase;
use app\common\modules\refund\product\ShopRefundOrder;

class RefundOrderFactory
{
    /**
     * @var Order
     */
    protected $order;

    protected $refundOrder;

    protected $refundConfigs;

    /**
     * @var null|static
     */
    protected static $instance = null;

    /**
     * 单例缓存
     * @return null|static
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance =  new self();
        }
        return self::$instance;
    }

    public static function newInstance()
    {
        self::$instance = new self();
        return self::$instance;
    }

    public function forgetInstance()
    {
        self::$instance = null;
    }

    public function __construct()
    {
        $this->refundConfigs = \app\common\modules\shop\ShopConfig::current()->get('shop-foundation.refund.order-type');
    }


    public function getConfigs()
    {
        return $this->refundConfigs;
    }

    public function getRefundDetail(RefundApply $refundApply, $port = 'frontend')
    {
        // 从配置文件中载入,按优先级排序
        $refundConfigs = collect($this->getConfigs())->sortByDesc('priority');

        //遍历取到第一个通过验证的订单类型返回
        foreach ($refundConfigs as $configItem) {
            /**
             * @var RefundOrderTypeBase $orderType
             */
            $refundService = call_user_func($configItem['class'], $refundApply->order, $port);
            $refundService->setRefundApply($refundApply);
            //通过验证返回
            if (isset($refundService) && $refundService->isBelongTo()) {
                return $refundService;
            }

        }

        $defaultRefundService = new ShopRefundOrder($refundApply->order, $port);
        $defaultRefundService->setRefundApply($refundApply);

        //没有对应订单类型，返回默认订单类型
        return $defaultRefundService;
    }

    /**
     * @param Order $order
     * @param string $port
     * @return RefundOrderTypeBase|ShopRefundOrder
     */
    public function getRefundOrder(Order $order, $port = 'frontend')
    {
        // 从配置文件中载入,按优先级排序
        $refundConfigs = collect($this->getConfigs())->sortByDesc('priority');

        //遍历取到第一个通过验证的订单类型返回
        foreach ($refundConfigs as $configItem) {
            /**
             * @var RefundOrderTypeBase $orderType
             */
            $orderType = call_user_func($configItem['class'], $order, $port);
            //通过验证返回
            if (isset($orderType) && $orderType->isBelongTo()) {
                return $orderType;
            }

        }

        //没有对应订单类型，返回默认订单类型
        return new ShopRefundOrder($order, $port);
    }

}