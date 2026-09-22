<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2021/8/26
 * Time: 16:20
 */

namespace app\common\providers;


use app\common\payment\method\integration\AlipayIntegrationPayment;
use app\common\payment\method\integration\HfkjIntegrationPayment;
use app\common\payment\method\integration\HfMiniIntegrationPayment;
use app\common\payment\method\integration\LklIntegrationPayment;
use app\common\payment\method\integration\WechatIntegrationPayment;
use app\common\payment\method\PaymentGatewayInterface;
use app\common\payment\PaymentConfig;
use app\common\payment\PaymentDirector;
use app\common\payment\PaymentManager;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider implements DeferrableProvider
{


    public function register()
    {
        $this->app->bind('Payment', PaymentDirector::class);
        $this->app->singleton(PaymentManager::class, function() {
            return new PaymentManager();
        });
    }

    public function boot()
    {
        app(PaymentManager::class)->register('wxIntegrationPay',WechatIntegrationPayment::class);
        app(PaymentManager::class)->register('zfbIntegrationPay',AlipayIntegrationPayment::class);
        app(PaymentManager::class)->register('lklIntegrationPay',LklIntegrationPayment::class);
        app(PaymentManager::class)->register('hfkjIntegrationPay',HfkjIntegrationPayment::class);
        app(PaymentManager::class)->register('hfMiniIntegrationPay',HfMiniIntegrationPayment::class);


        $plugins = app('plugins')->getEnabledPlugins();
        foreach ($plugins as $plugin) {
            if (method_exists($plugin->app(), 'paymentConfig')) {
                $plugin->app()->paymentConfig();
            }
        }
    }



    public function provides()
    {
        return ['Payment',PaymentManager::class];
    }

}