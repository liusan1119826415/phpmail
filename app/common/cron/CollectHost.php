<?php

namespace app\common\cron;

use Illuminate\Foundation\Bus\DispatchesJobs;

class CollectHost
{
    use DispatchesJobs;

    protected $url;

    public function __construct()
    {
        $this->url = 'https://yun.yunzmall.com';
    }

    public function handle()
    {
        $host_message = json_decode(file_get_contents(base_path('static/yunshop/js/host.js')), true);
        $host = $host_message['host'] ?: '';
        $key = $host_message['key'] ?: '';
        $secret = $host_message['secret'] ?: '';
        $data = [
            'host' => $host,
            'plugins' => $this->getPlugins(),
            'key' => $key,
            'secret' => $secret,
        ];
        $url = $this->url . '/api/plugin-collect/plugin-collect';
        $result = \Curl::to($url)
            ->withData($data)
            ->asJsonResponse(true)
            ->post();
        if ($result['result'] != 1) {
            \Log::debug('------授权系统请求获取插件信息接口失败------', $result);
        }
    }
    public function getPlugins()
    {
        $plugin_name = [];
        $plugins = app('plugins')->getPlugins()->toArray();
        foreach ($plugins as $plugin) {
            $plugin_name[] = $plugin['name'];
        }
        return $plugin_name;
    }
}