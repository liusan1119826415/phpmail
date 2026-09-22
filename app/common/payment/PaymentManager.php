<?php

namespace app\common\payment;

use app\common\models\PayType;
use app\common\payment\method\PaymentGatewayInterface;

class  PaymentManager
{
    protected $gateways;

    protected $listPayType;

    public function __construct()
    {
        $this->gateways = collect();
        $this->listPayType = PayType::get();
    }

    public function register(string $name, $gateway)
    {
        $this->gateways->put($name, $gateway);
    }

    public function get(string $name): PaymentGatewayInterface
    {
        return app()->make($this->gateways->get($name));
    }

    public function all(): \Illuminate\Support\Collection
    {
        return $this->gateways;
    }


    public function getScenePayments($scene): array
    {
        $codes = [];
        foreach ($this->gateways as $gateway) {
            $payment = app()->make($gateway);
            if (in_array($scene, $payment->getScene())) {
                $codes[$payment->code] = $payment->getName();
            }
        }
        return $codes;
    }

    public function getPayType(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->listPayType;
    }

}