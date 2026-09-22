<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/7/19
 * Time: 17:53
 */

namespace app\frontend\modules\payment\services;

use app\common\models\Order;
use app\common\modules\shop\ShopConfig;
use app\common\models\OrderPay;
use app\frontend\modules\payment\services\page\DefaultPaymentPage;
use app\frontend\modules\payment\services\page\PaymentPageInterface;

class PayPageService
{

    public $productCollect;

    public $moduleKey = [ 'head', 'order_info','reward_list','carousel', 'goods'];

    /**
     * @return \Illuminate\Support\Collection
     */
    public function pageConfig(Order $order)
    {
        $configs = ShopConfig::current()->get('shop-foundation.pay-page.type');

        return collect($configs)->map(function ($configItem) use ($order) {
            $class = call_user_func($configItem['class'], $order);
            $class->setPriority($configItem['priority']);
            $class->setUniqueCode($configItem['code']);
            return $class;
        })->sortByDesc(function ($class) {
            return $class->priority;
        });

    }

    public function eachOrderConfig(Order $order)
    {
        $pageConfig = $this->pageConfig($order);

        /**
         * @var PaymentPageInterface $concreteProduct
         */
        foreach ($pageConfig as $concreteProduct) {
            //第一个可用的配置
            if ($concreteProduct->usable()) {
                return $concreteProduct;
            }
        }

        return new DefaultPaymentPage($order);
    }

    public function mergePayPage(OrderPay $orderPay)
    {
//        $order = Order::find(895); //837、895、789、737、940
//        $product = $this->eachOrderConfig($order);
//        $data[] = $product->toData();
//        return $data;
//        dd($data);

        $data = [];
        foreach ($orderPay->orders as $order) {
            $product = $this->eachOrderConfig($order);
            $data[] = $product->toData();

        }
        return $data;
    }

    public function goodsPaging(Order $order)
    {
        $product = $this->eachOrderConfig($order);

        $goodsList = $product->moduleData('goods');
        return $goodsList;
    }


}