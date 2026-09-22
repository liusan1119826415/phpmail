<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/10/8
 * Time: 16:38
 */

namespace app\backend\modules\withdraw\services;


use app\backend\modules\withdraw\widget\BaseWithdrawSetWidget;

class WithdrawSetService
{
    public function view($request = null)
    {
        $result = $this->widgetCollect()->toArray();

        return $result;
    }

    /**
     * @param null $request
     * @return \Illuminate\Support\Collection
     */
    public function widgetCollect($request = null)
    {
        $widgets = \app\common\modules\widget\Widget::current()->getItem('vue-withdraw');

        $widgetClassCollect = collect([]);

        $withdrawSetting =  \Setting::getByGroup('withdraw');

        foreach ($widgets as $configItem) {

            if (class_exists($configItem['class'])) {
                /**
                 * @var BaseWithdrawSetWidget $widgetClass
                 */
                $widgetClass = new $configItem['class']();
                //通过验证返回
                if ($widgetClass->insideAuthorization()) {
                    $widgetClass->setSetting($withdrawSetting);
                    $widgetClass->setTitle($configItem['title']);

                    $widgetClassCollect->push($widgetClass);
                }
            }
        }

        return $widgetClassCollect;
    }

    public function setSave()
    {
        $this->widgetCollect()->each(function (BaseWithdrawSetWidget $widget) {
            $widget->saveSubmit();
        });

        return true;
    }
}