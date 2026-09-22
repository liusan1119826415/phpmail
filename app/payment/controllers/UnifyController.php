<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 24/03/2017
 * Time: 01:07
 */

namespace app\payment\controllers;


use app\common\payment\PaymentManager;
use app\payment\PaymentController;

class UnifyController extends PaymentController
{
    public function notify()
    {
        $code = request()->input('payCode');
        $result = app(PaymentManager::class)->get($code)->callbackPayment();
        if ($result['pay_data']) {
            $this->payResutl($result['pay_data']);
        }
        if($result['is_return']){
            return $result['response'];
        }
        echo $result['response'];
        exit;
    }
}
