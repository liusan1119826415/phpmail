<?php

namespace app\backend\modules\setting\controllers;

use app\common\components\BaseController;

class YunOpenController extends BaseController
{
    public function index()
    {
        if (request()->ajax()) {
            $yun_open_data = \Setting::get('shop.yun_open') ?: null;
            foreach ($yun_open_data as &$openData) {
                $openData = str_repeat('*', 10);
            }
            $data['set'] = $yun_open_data;
            return $this->successJson('ok', $data);
        }
        return view('setting.yun-open.index');
    }


    public function save()
    {
        $data = request('data');
        $yun_open_data = \Setting::get('shop.yun_open');
        foreach ($data as $setKey => &$set) {
            $set = $set === str_repeat('*', 10) ? $yun_open_data[$setKey] : $set;
        }
        $result = \Setting::set('shop.yun_open', $data);
        return $this->successJson();
    }

}
