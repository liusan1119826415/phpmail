<?php
/****************************************************************
 * Author:  king -- LiBaoJia
 * Date:    2020/2/24 10:16 AM
 * Email:   livsyitian@163.com
 * QQ:      995265288
 * IDE:     PhpStorm
 * User:
 ****************************************************************/


namespace app\backend\modules\map\controllers;


use app\common\components\BaseController;
use app\common\facades\Setting;
use Illuminate\Support\Facades\DB;

class SettingController extends BaseController
{
    public function index()
    {
        if ($this->postData()) {
            return $this->store();
        }

        return view('map.setting', $this->viewData());
    }

    /**
     * 数据存储
     */
    private function store()
    {
        DB::beginTransaction();
        $data = $this->postData();
        if (isset($data['qq_map_web_key']) && !strpos($data['qq_map_web_key'], '*')) {
            Setting::set('plugin.min_app.qq_map_web_key', trim($data['qq_map_web_key']));

        }
        if (isset($data['qq_map_web_sign']) && !strpos($data['qq_map_web_sign'], '*')) {
            Setting::set('plugin.min_app.qq_map_web_sign', trim($data['qq_map_web_sign']));
        }
        unset($data['qq_map_web_key']);
        unset($data['qq_map_web_sign']);
        $map = Setting::get('map.a_map');

        $data['web'] = $data['web'] ? trim($data['web']) : '';
        $data['web_js_key'] = $data['web_js_key'] ? trim($data['web_js_key']) : '';
        $data['web_js_secret_key'] = $data['web_js_secret_key'] ? trim($data['web_js_secret_key']) : '';
        if (strpos($data['web'], '*')) {
            $data['web'] = $map['web'];
        }
        if (strpos($data['web_js_key'], '*')) {
            $data['web_js_key'] = $map['web_js_key'];
        }
        if (strpos($data['web_js_secret_key'], '*')) {
            $data['web_js_secret_key'] = $map['web_js_secret_key'];
        }

        Setting::set("map.a_map", $data);
        Setting::set('map.a_map.synchronize_store', 1);
        if (!$this->webConfig()) {
            DB::rollBack();
            return $this->errorJson('地图设置失败');
        }
        DB::commit();
        return $this->successJson('地图设置成功');
    }

    /**
     * 提交数据
     *
     * @return array
     */
    private function postData()
    {
        $request = request()->input('a_map', []);

        return $request;
    }

    /**
     * view 数据
     *
     * @return array
     */
    private function viewData()
    {
        $map = Setting::get('map.a_map');

        //兼容同步门店配送规则
        if (!$map['web'] && !$map['synchronize_store']) {
            Setting::set('map.a_map.synchronize_store', 1);
            $map['synchronize_store'] = 1;
        }
        $min_app_set = \Setting::get('plugin.min_app');
        $map['qq_map_web_key'] = $min_app_set['qq_map_web_key'] ?? '';
        $map['qq_map_web_sign'] = $min_app_set['qq_map_web_sign'] ?? '';
        if ($map['qq_map_web_key']) {
            $map['qq_map_web_key'] = $this->substrCut($map['qq_map_web_key']);
        }
        if ($map['qq_map_web_sign']) {
            $map['qq_map_web_sign'] = $this->substrCut($map['qq_map_web_sign']);
        }
        if ($map['web']) {
            $map['web'] = $this->substrCut($map['web']);
        }
        if ($map['web_js_key']) {
            $map['web_js_key'] = $this->substrCut($map['web_js_key']);
        }
        if ($map['web_js_secret_key']) {
            $map['web_js_secret_key'] = $this->substrCut($map['web_js_secret_key']);
        }
        return ['map' => $map];
    }

    public function synchronize()
    {
        if (!app('plugins')->isEnabled('store-delivery')) {
            return $this->errorJson('请先开启门店配送插件');
        }

        if (Setting::get('map.a_map.synchronize_store') == 1) {
            return $this->errorJson('同步失败，已经同步过数据或者修改过地图KEY');
        }

        $mapKey = Setting::get('map.a_map.web');
        if (!$mapKey) {
            return $this->errorJson('同步失败，地图KEY没有设置');
        }

        Setting::set('map.a_map.synchronize_store', 1);

        //更新门店地图key
        $uniacid = \Yunshop::app()->uniacid;
        $list = DB::table('yz_store_geo_fence')
            ->select('yz_store_geo_fence.*')
            ->leftJoin('yz_store', 'yz_store_geo_fence.store_id', '=', 'yz_store.id')
            ->where('yz_store.uniacid', $uniacid)
            ->get()->toArray();

        foreach ($list as $key => $value) {
            $webKey = Setting::get("store_cashier_{$value['store_id']}.a_map.web_key") ?? '';
            if (!$webKey) {
                Setting::set("store_cashier_{$value['store_id']}.a_map", ['web_key' => $mapKey]);
            }
        }
        return $this->successJson('地图KEY同步成功');
    }

    // 在前端生成shopConfig.js 文件
    private function webConfig()
    {
        $aMapConfig = \Illuminate\Support\Facades\DB::table('yz_setting')
            ->where('group', 'map')
            ->get()
            ->toArray();
        $data = [];
        foreach ($aMapConfig as $item) {
            $item['value'] = unserialize($item['value']);
            if (!$item['value']['web_js_key'] || !$item['value']['web_js_secret_key']) {
                continue;
            }
            $data[$item['uniacid']] = [
                'key' => $item['value']['web_js_key'],
                'securityJsCode' => $item['value']['web_js_secret_key'],
            ];
        }
        $url = $this->getUrl();
        $shopConfig = fopen($url, 'w');
        if ($shopConfig) {
            fwrite($shopConfig, 'var $AMapConfig = ' . json_encode($data));
            fclose($shopConfig);
            return true;
        } else {
            fclose($shopConfig);
            return false;
        }
    }

    private function getUrl() {
        $re = "/yun_shop$/";
        if (preg_match($re, base_path())) {
            $url = base_path() . DIRECTORY_SEPARATOR . "shopConfig.js";
        } else {
            $url = base_path() . DIRECTORY_SEPARATOR . "addons" . DIRECTORY_SEPARATOR . "yun_shop" . DIRECTORY_SEPARATOR . "shopConfig.js";
        }

        return $url;
    }

    private function substrCut($str)
    {
        $strlen = mb_strlen($str, 'utf-8');
        $firstStr = mb_substr($str, 0, 5, 'utf-8');
        $lastStr = mb_substr($str, -5, 5, 'utf-8');

        if ($strlen < 2) {
            return "****";
        } else {
            $num = $strlen - 2;
            return $strlen == 2 ? $firstStr . str_repeat('*', mb_strlen($str, 'utf-8') - 1) : $firstStr . str_repeat("*", $num > 4 ? 4 : $num) . $lastStr;
        }
    }
}
