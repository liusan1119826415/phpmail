<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/9/25
 * Time: 17:07
 */

namespace app\backend\modules\withdraw\widget;

use app\common\helpers\Url;
use app\framework\Http\Request;
use app\common\facades\Setting;
use Illuminate\Contracts\Support\Arrayable;

abstract class BaseWithdrawSetWidget  implements Arrayable
{
    public $title; //挂件名称

    public $code; //挂件唯一标识需与组件文件名称保持一致

    public $widget_key = ''; //挂件数据键名

    public $allSetting;

    public function __construct()
    {

    }

    final public function setSetting($groupSetting)
    {
        $this->allSetting = $groupSetting;
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function currentConfig()
    {


        $setting = $this->allSetting[$this->getWidgetKey()];

        return $setting?:[];

    }

    public function saveSubmit()
    {
        $attributes = $this->fillAttributes();

        if ($this->saveValidator($attributes)) {
            $this->updRecord($this->currentConfig(), $attributes);

            Setting::set('withdraw.' . $this->getWidgetKey(), $attributes);
        }
    }


    //filter
    public function insideAuthorization()
    {
        return $this->usable();
    }

    public function usable()
    {
        return true;
    }

    /**
     * 保存修改日志
     * @param $oldData
     * @param $newData
     */
    protected function updRecord($oldData, $newData)
    {

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

    protected function defaultHandle($data)
    {
        return array_merge($this->defaultData(), $data);
    }

    //默认数据
    public function defaultData()
    {
        return [];
    }

    protected function getPath($path)
    {
        return  Url::shopUrl($path);
    }

    /**
     * 保存验证
     * @param array $attributes
     * @return bool
     */
    public function saveValidator($attributes = [])
    {
        return true;
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
}