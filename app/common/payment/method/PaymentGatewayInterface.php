<?php

namespace app\common\payment\method;

interface PaymentGatewayInterface
{
    public function processPayment(array $data);

    public function doRefund(array $data);

    public function doWithdraw();
}