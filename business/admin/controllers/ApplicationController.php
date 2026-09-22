<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2021/9/22
 * Time: 13:37
 */

namespace business\admin\controllers;

use business\common\controllers\components\BaseController;
use business\common\services\BusinessService;
use business\common\services\SettingService;

class ApplicationController extends BaseController
{

    public $type = [
        ['name' => '办公管理', 'key' => 'businessApplicationWork'],
        ['name' => '销售管理', 'key' => 'businessApplicationSales'],
        ['name' => '工具软件', 'key' => 'businessApplicationTool'],
    ];

    public function getApplicationList()
    {

        $plugin_function = \app\common\modules\shop\ShopConfig::current()->get(SettingService::BUSINESS_PLUGIN_KEY) ?: [];
        $type_key = array_column($this->type, 'key');
        $return_data = [];
        $auth = BusinessService::checkBusinessRight();
        foreach ($plugin_function as $v) {
            $class = $v['class'];
            $function = $v['function'];
            if (!method_exists($class, $function)) continue;
            $res = $class::$function();
            if (!$res || !in_array($res['type'], $type_key)) continue;
            if (!$this_page_route = $auth['page_route'][$res['plugin']]) continue;
            foreach ($this_page_route as $vv) {
//                if ($vv['route'] == $res['route']) {
                    if ($vv['can'] || $auth['identity'] > 1) {
                        $plugin_name = SettingService::changePluginName($res['plugin']);
                        $res['icon'] = file_exists(base_path('static/yunshop/plugins/list-icon/icon/' . $plugin_name . '.png')) ? static_url("yunshop/plugins/list-icon/icon/{$plugin_name}.png") : static_url("yunshop/plugins/list-icon/icon/default.png");
//                        $res['icon'] = file_exists(base_path('static/yunshop/plugins/list-icon/img/' . $res['icon'] . '.png')) ? static_url("yunshop/plugins/list-icon/img/{$res['icon']}.png") : static_url("yunshop/plugins/list-icon/img/default2.png");
//                        $res['icon'] = request()->getSchemeAndHttpHost() . $res['icon'];
                        $return_data[$res['type']][] = $res;
                        break;
                    }
//                }
            }
        }
        return $this->successJson('成功', ['plugin_list' => $return_data, 'type' => $this->type]);
    }

}
