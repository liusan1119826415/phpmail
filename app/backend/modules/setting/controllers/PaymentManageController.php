<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/11/1
 * Time: 17:04
 */

namespace app\backend\modules\setting\controllers;

use app\backend\modules\setting\payment\BasePaymentSetWidget;
use app\common\components\BaseController;
use app\common\helpers\Url;
use app\common\models\Goods;

class PaymentManageController extends BaseController
{
    public function index()
    {

        $data = [];
        return view('setting.pay.vue-index', [
            'data' => json_encode($data),
        ])->render();
    }

    public function payWidget()
    {

        $data = $this->widgetCollect()->map(function ($widgetClass) {
            return $widgetClass->toArray();
        });


        return $this->successJson('payWidget', $data);
    }


    public function submitSave()
    {
        $widgetKey = request()->input('tab');
        $widgetData = request()->input('data');

        /**
         * @var $currentWidget BasePaymentSetWidget
         */
        $currentWidget = $this->widgetCollect()->first(function (BasePaymentSetWidget $widgetClass) use ($widgetKey) {
            return $widgetClass->getWidgetKey() == $widgetKey;
        });

        $currentWidget->setSubmitParam($widgetData);

        $currentWidget->saveSubmit();


        return $this->successJson('成功');
    }

    /**
     * @param null $request
     * @return \Illuminate\Support\Collection
     */
    public function widgetCollect()
    {

        $widgets = \app\common\modules\widget\Widget::current()->getItem('pay_set');

        $widgetClassCollect = collect([]);

        foreach ($widgets as $configItem) {

            if (class_exists($configItem['class'])) {
                /**
                 * @var BasePaymentSetWidget $widgetClass
                 */
                $widgetClass = new $configItem['class']();
                //通过验证返回
                if ($widgetClass->insideAuthorization()) {

                    $widgetClass->setTitle($configItem['title']);

                    $widgetClassCollect->push($widgetClass);
                }
            }
        }

        $widgetClassCollect = $widgetClassCollect->sortByDesc(function (BasePaymentSetWidget $widgetClass) {
            return $widgetClass->getPriority();
        });

        return $widgetClassCollect;
    }

    //支付设置上传文件接口
    public function newUpload()
    {
        $updatefile = $this->updateFile($_FILES);
        if (!is_null($updatefile)) {
            if ($updatefile['status'] == -1) {
                return $this->errorJson('上传文件类型错误', Url::absoluteWeb('setting.payment-manage.index'));
            }

            if ($updatefile['status'] == 0) {
                return $this->errorJson('上传文件失败', Url::absoluteWeb('setting.payment-manage.index'));
            }

            return $this->successJson('文件上传成功', ['data' => $updatefile]);
        } else {
            return $this->errorJson('上传文件类型错误', Url::absoluteWeb('setting.payment-manage.index'));
        }
    }

    private function updateFile($file_input)
    {
        $data = [];
        $file_input = array_keys($file_input)[0];

        $file = \Request::file($file_input);

        if ($file->isValid()) {
            // 获取文件相关信息
            $originalName = $file->getClientOriginalName(); // 文件原名
            $ext = $file->getClientOriginalExtension();     // 扩展名
            $realPath = $file->getRealPath();   //临时文件的绝对路径

            $upload_file = \YunShop::app()->uniacid . '_' . $originalName;

            if (!in_array($ext,  ['pem', 'crt'])) {
                return ['status' => -1];
            }

            $bool = \Storage::disk('cert')->put($upload_file, file_get_contents($realPath));

            if ($bool) {
                $path_name = storage_path('cert/' . $upload_file);
                $data['key'] = $file_input;
                $data['value'] = $path_name;
                $data[$file_input] = $path_name;
                return ['status' => 1, 'data' => $data];
            } else {
                return ['status' => 0];
            }
        }
        return null;
    }

}