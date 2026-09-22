<?php

namespace app\common\providers;

use app\common\payment\method\integration\AlipayIntegrationPayment;
use app\common\payment\method\integration\LklIntegrationPayment;
use app\common\payment\method\integration\WechatIntegrationPayment;
use app\common\payment\PaymentDirector;
use app\common\payment\PaymentManager;
use app\exports\Export;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Finder\Finder;

class ExportServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register()
    {
        $this->app->singleton('Export', Export::class);
    }

    public function boot()
    {




        $plugins = app('plugins')->getEnabledPlugins();
        foreach ($plugins as $plugin) {
            if (method_exists($plugin->app(), 'paymentConfig')) {
                $plugin->app()->paymentConfig();
            }
        }
    }



    public function provides()
    {
        return ['Export'];
    }
}
