<?php

namespace app\common\services\yunopen;

use app\common\exceptions\ShopException;
use Illuminate\Support\Carbon;
use Ixudra\Curl\Facades\Curl;

class YunOpenClient
{

    const API = "https://openapi.yunzmall.com";
    const TEST_API = "https://dev3.yunzmall.com";
    protected $apiAppKey;
    protected $appSecret;
    protected $apiUrl;


    public function __construct()
    {
        $this->apiAppKey = \Setting::get('shop.yun_open.api_app_key');
        $this->appSecret = \Setting::get('shop.yun_open.app_secret');
        $this->apiUrl = self::API;
//        if (strpos(request()->getHttpHost(), 'dev') === 0 || strpos(request()->getHttpHost(), 'release') === 0) {//测试用
//            $this->apiUrl = self::TEST_API;
//        }
        if (!$this->apiAppKey || !$this->appSecret) {
            throw new ShopException(!$this->apiAppKey ? "缺少App Key！" : "缺少App Secret！");
        }
    }

    /**
     * @param string $apiUrl
     */
    public function setApiUrl(string $apiUrl)
    {
        $this->apiUrl = $apiUrl;
        return $this;
    }

    /**
     * 签名
     * @param $headers
     * @param $data
     * @return string
     */
    protected function getSign($headers, $data = [])
    {
        ksort($headers);
        $str_key = "";
        foreach ($headers as $k => $v) {
            $str_key .= $k . $v;
        }
        $str_key .= $this->appSecret;
        $str_key .= json_encode($data);
        $sign = strtoupper(md5(sha1($str_key)));
        return $sign;
    }

    protected function getHeaders()
    {
        $data['Api-App-Key'] = $this->apiAppKey;
        $data['Api-Time-Stamp'] = Carbon::now()->getTimestampMs();
        $data['Api-Nonce'] = md5($this->apiAppKey . $data['Api-Time-Stamp']);
        return $data;
    }

    /**
     * 自定义请求
     * @param $url
     * @param $data
     * @return \Ixudra\Curl\Builder
     * @throws ShopException
     */
    public function request($url, $data = [])
    {
        $headers = $this->getHeaders();
        $headers['Api-Sign'] = $this->getSign($headers, $data);
        $url = $this->getUrl($url);
        return Curl::to($url)->withHeaders($headers)->asJsonRequest()->withData($data);

    }

    /**
     * post请求
     * @param $url
     * @param $data
     * @return array|mixed|\stdClass
     * @throws ShopException
     */
    public function post($url, $data = [])
    {
        try {
            return $this->request($url, $data)
                ->asJsonResponse(true)
                ->post();
        } catch (ShopException $e) {
            throw new ShopException($e->getMessage());
        }
    }

    /**
     * 请求接口地址
     * @param $url
     * @return string
     */
    protected function getUrl($url)
    {
        $api_url = $this->apiUrl;
        if (strpos($url, $api_url) !== false) {
            return $url;
        }

        if (strpos($url, 'http://') !== false || strpos($url, 'https://') !== false) {
            return $url;
        }

        $serverUrl = rtrim($api_url, '/') . '/' . ltrim($url, '/');

        return $serverUrl;
    }

}
