<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/11/1
 * Time: 17:38
 */

namespace app\backend\modules\setting\payment;


use app\common\facades\Setting;
use app\common\helpers\Url;

abstract class BasePaymentSetWidget
{
    public $title; //挂件名称

    public $priority = 0;

    protected $attributes = []; //提交保存数据

    /**
     * @var array
     */
    protected $submitParam = [];

    final public function saveSubmit()
    {
        $this->attributes = $this->fillAttributes();

        if ($this->saveValidator()) {

            $this->updRecord();

            $this->saveOperation();
        }
    }

    //支付设置默认都是保存到shop.pay里的，如果不是需要重写这个方法
    public function saveOperation()
    {
        $pay = Setting::get('shop.pay')?:[];

        $paySet = array_merge($pay, $this->getAttributes());

        Setting::set('shop.pay', $paySet);
    }

    public function toArray()
    {
        return [
            'title' => $this->getTitle(),
            'widget_key' => $this->getWidgetKey(),
            'data' => $this->getData(),
            'page_path' => $this->pagePath(),
            'component_name' => $this->componentName(),
        ];
    }




    public function setSubmitParam($data = [])
    {
        $this->submitParam = $data;
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getPriority()
    {
        return $this->priority;
    }

    public function getSubmitParam()
    {
        return $this->submitParam;
    }

    public function getAttributes()
    {
        return $this->attributes;
    }


    /**
     * 保存修改日志
     */
    protected function updRecord()
    {

    }

    //挂件是否显示处理
    protected function usable()
    {
        return true;
    }

    /**
     * 保存验证
     * @return bool
     */
    public function saveValidator()
    {
        return true;
    }

    //支付默认设置数据
    public function defaultData()
    {
        return [];
    }

    //当前支付已存在的设置
    public function currentConfig()
    {
        return [];
    }


    //挂件页面文件名-组件名称
    abstract public function componentName();

    //挂件页面路径给前端引入
    abstract public function pagePath();

    /**
     * 返回设置参数
     * @return mixed|array
     */
    abstract public function getData();

    /**
     * 保存设置数据处理
     * @return mixed|array
     */
    abstract public function fillAttributes();

    /**
     * 设置保存键
     * @return mixed|string
     */
    abstract public function getWidgetKey();

    //filter
    final public function insideAuthorization()
    {
        return $this->usable();
    }

    protected function getPath($path)
    {
        return  Url::shopUrl($path);
    }

}