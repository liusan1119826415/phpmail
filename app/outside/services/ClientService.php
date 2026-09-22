<?php
/**
 * Created by PhpStorm.
 * 
 *
 *
 * Date: 2022/1/7
 * Time: 15:15
 */

namespace app\outside\services;


use app\common\exceptions\AppException;
use app\outside\modes\OutsideAppSetting;
use app\common\exceptions\ApiException;
use Illuminate\Support\Facades\Redis;
use Ixudra\Curl\Facades\Curl;


class ClientService
{
    private $route; //方法
    private $app_id; //应用AppID
    private $secret; //密钥字符串

    private $data = []; //data参数

    private $sign_type = "MD5"; //SHA256 MD5

    private $version = 1;

    private $openLog = false;

    public function __construct()
    {
        $this->init();
    }

    /**
     * 配置信息
     */
    public function init()
    {
        $outsideApp = OutsideAppSetting::current();

        $this->app_id = $outsideApp->app_id;
        $this->secret = $outsideApp->app_secret;

        $this->setData('app_id', $outsideApp->app_id);
        $this->setData('sign_type', $this->sign_type);
        $this->setData('ver', $this->version);
    }

    public function getSecret()
    {
        return $this->secret;
    }

    /**
     * @param $bool boolean
     */
    public function switchLog($bool = true)
    {
        $this->openLog = $bool;
    }


    public function setRoute($route)
    {
        $this->route = $route;
    }

    public function getRoute()
    {
        return $this->route;
    }


    public function initData($data)
    {
        $this->data = $data;
    }

    public function setData($key, $value)
    {
        $this->data[$key] = $value;
    }

    /**
     * 通过键获取 data 指定值
     * @param $key
     * @return mixed|string
     */
    public function getData($key)
    {
        return $this->data[$key] ? $this->data[$key] : '';
    }

    public function getAllData()
    {
        return $this->data;
    }


    public function get($url)
    {
        $requestData = $this->getAllData();

        $requestData['sign'] = $this->autograph($requestData['sign_type']);
        dd($requestData);

        $result = $this->httpRequest($url, $requestData,'GET');

        return $result;
    }

    /**
     * @param $url
     * @param $data
     *
     */
    public function post($url = '')
    {
        $requestData = $this->getAllData();

        $requestData['sign'] = $this->autograph($requestData['sign_type']);

        $result = $this->httpRequest($url, $requestData);

        return $result;


    }



    /**
     * @param $url
     * @param array $data
     * @param string $method
     * @return mixed
     * @throws AppException
     */
    public function httpRequest($url, $data = [], $method = 'POST')
    {

        if ($this->getRoute()) {
            $url = $url. ltrim($this->getRoute(),'/');
        }


        $requestData = $data ?: [];

        $curl = Curl::to($url)->asJson(true)->returnResponseArray();

        if ($method == "POST") {

            $result = $curl->withHeaders(['Content-Type' => 'application/json'])->withData($requestData)->post();
        } else {
            $result = $curl->withData($requestData)->get();
        }

        if ($this->openLog) {
            \Log::debug('<---------对外接口请求:',[
                'url' => $url,
                'request' => $data,
                'response' => $result,
            ]);

        }

        if ($result['status'] != 200) {
            throw new AppException("请求失败状态码：{$result['status']}");
        }

        return $result['content'];
    }

    protected function autograph($type)
    {
        if (strtoupper($type) == 'SHA256') {
            return  $this->verifySha256();
        }

        return $this->verifyMd5();
    }

    protected function verifySha256()
    {
        $hashSign = hash_hmac('sha256', $this->toQueryString($this->getAllData()), $this->getSecret());

        return $hashSign;
    }

    protected function verifyMd5()
    {
        return strtoupper(md5($this->toQueryString($this->getAllData()).'&secret='.$this->getSecret()));
    }

    /**
     * 将参数转换成k=v拼接的形式
     * @param $parameter
     * @return string
     */
    public function toQueryString($parameter)
    {

        //按key的字典序升序排序，并保留key值
        ksort($parameter);

        $strQuery="";
        foreach ($parameter as $k=>$v){

            //不参与签名、验签
            if($k == "sign"){ continue; }

            if($v === null) {$v = '';}


            if (is_array($v)) {
                $v = json_encode($v);
            }

            $strQuery .= strlen($strQuery) == 0 ? "" : "&";
            $strQuery.= $k."=".$v;
        }

        return $strQuery;
    }

}