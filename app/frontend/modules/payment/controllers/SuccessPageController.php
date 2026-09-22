<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/7/31
 * Time: 16:40
 */

namespace app\frontend\modules\payment\controllers;


use app\common\exceptions\AppException;
use app\common\helpers\Url;
use app\common\models\Order;
use app\common\models\OrderPay;
use app\common\components\ApiController;
use app\frontend\modules\payment\services\PayPageService;

class SuccessPageController  extends ApiController
{
    public function index()
    {

        $pay_id = intval(request()->input('order_pay_id'));

        //$orderPay = OrderPay::find(58420);

        $orderPay = OrderPay::where('id', $pay_id)->with(['orders'])->first();

        if (!$orderPay) {
            throw new AppException('支付记录不存在');
        }

        $data = (new PayPageService)->mergePayPage($orderPay);

        return $this->successJson('pay success', $data);

    }

    public function goodsPage()
    {
        $order_id = intval(request()->input('order_id'));

        $order = Order::find($order_id); //837、895、789、737、940

        if (!$order) {
            throw new AppException('订单记录不存在');
        }

        $data = (new PayPageService())->goodsPaging($order);

        return $this->successJson('list', $data);
    }

    protected function getPayType($sn)
    {
        if (!empty($sn)) {
            $tag = substr($sn, 0, 2);
            return $tag;
        }
        return '';
    }
}