<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/3/9
 * Time: 下午5:26
 */

namespace app\backend\modules\setting\controllers;

use app\backend\modules\uploadVerificate\UploadVerificationBaseController;
use app\common\components\BaseController;
use app\common\facades\Setting;
use app\common\helpers\Cache;

class LangController extends UploadVerificationBaseController
{
    private $locale = 'zh_cn';

    public function index()
    {
        if (request()->setdata) {
            return $this->store();
        }
        if (request()->ajax()) {
            $widgets = \app\common\modules\widget\Widget::current()->getItem('lang');
            $langData = $this->langData()['set'];

            foreach ($widgets as $widget) {
                foreach ((new $widget['class'])->getData() as $tab => $list) {
                    $set_data = $langData[$tab];
                    $widget_data = [];
                    foreach ($list['data'] as $value) {
                        $widget_data[] = [
                            'key' => $value['key'],
                            'name' => $value['name'] ?? $value['default'],
                            'default' => $value['default'],
                            'value' => $set_data[$value['key']] ?: $value['default'],
                            'remark' => $value['remark'],
                        ];
                    }
                    $data[$tab]['data'] = $widget_data;
                    $data[$tab]['name'] = $list['tab'];
                }
            }
            return $this->successJson('ok', $data);
        }
        return view('setting.shop.lang');
    }

    private function store()
    {
        $data['lang'] = $this->locale;
        $setdata = request()->setdata;
        $set = [];
        foreach ($setdata as $key => $value) {
            $save_data = [];
            foreach ($value['data'] as $value2) {
                $save_data[$value2['key']] = $value2['value'] ?: $value2['default'];
            }
            $set[$key] = $save_data;
        }
        $data[$this->locale] = $set;

        if (Setting::set('shop.lang', $data)) {
            Cache::forget('lang_setting');
            return $this->successJson('语言设置成功');
        }
        return $this->errorJson('语言设置失败');
    }

    /**
     * @return array
     */
    private function langData()
    {
        $lang = $this->langSet();

        return ['set' => $lang[$lang['lang']]];
    }

    /**
     * @return array
     */
    private function langSet()
    {
        return Setting::get('shop.lang', ['lang' => 'zh_cn']);
    }
}
