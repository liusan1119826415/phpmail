<?php

namespace app\common\services\asset;

class ShopAsset
{

    private $data;

    public function __construct()
    {
        if (app()->runningInConsole()) {
            $plugins = app('plugins')->getEnabledPlugins('*');
        } else {
            $plugins = app('plugins')->getEnabledPlugins();
        }
        $assets = [];
        foreach ((new AssetConfig())->getAssetConfig() as $key => $item) {
            foreach ($item as $id => $func) {
                array_set($assets[$key], $id, $func);
            }
        }
        foreach ($plugins as $plugin) {
            if (method_exists($plugin->app(), 'getAssetConfig')) {
                foreach ($plugin->app()->getAssetConfig() as $key => $item) {
                    foreach ($item as $id => $func) {
                        array_set($assets[$key], $id, $func);
                    }
                }
            }
        }
        $this->data = $assets;
    }

    public function getData($key = '')
    {
        if ($key) {
            return $this->data[$key] ?? [];
        }
        return $this->data;
    }
}
