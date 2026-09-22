<?php


namespace app\platform\modules\pluginsSetMeal\controllers;


use app\backend\modules\menu\Menu;
use app\common\models\Option;
use app\common\services\AuthCodeService;
use app\platform\controllers\BaseController;
use app\platform\modules\application\models\UniacidApp;
use app\platform\modules\pluginsSetMeal\models\PluginsMealModel;
use app\platform\modules\pluginsSetMeal\models\pluginsMealPlatform;
use Illuminate\Support\Facades\Cache;
use Ixudra\Curl\Facades\Curl;

class PluginsSetMealController extends BaseController
{
    public function pluginsList()
    {
        $type = request()->input('type');
        Menu::flush();
        $plugins = array_keys(app('plugins')->getPlugins()->toArray());

        $res = $this->getSourcePluginList();

        $data = [];
        $classification = $res['category_list'];


        if (empty($type)) {
            foreach ($res['list'] as $plugin) {
                if (in_array($plugin['name'],$plugins)) {
                    $data[] = [
                        'title' => $plugin['name'], //配合前端传值，并不是写反了
                        'name' => $plugin['title'],
                        'type' => $plugin['category_id']
                    ];
                }

            }
        } else {
            foreach ($res['list'] as $plugin) {
                if ($plugin['category_id'] == $type && in_array($plugin['name'],$plugins)) {
                    $data[] = [
                        'title' => $plugin['name'],
                        'name' => $plugin['title'],
                        'type' => $plugin['category_id']
                    ];
                }
            }
        }

        return $this->successJson('ok', [
            'data' => $data,
            'classification' => $classification
        ]);
    }

    public function addPluginsMeal()
    {
        $data = request()->all();
        $data['plugins'] = implode(',', $data['plugins']);
        $plugin = PluginsMealModel::where('name', $data['name'])->first();
        if ($plugin) {
            return $this->errorJson('该套餐名字已存在');
        }

        try {
            PluginsMealModel::create($data);
            return $this->successJson('套餐创建成功');
        } catch (\Exception $e) {
            return $this->errorJson('套餐创建失败,不能存在空值');
        }

    }

    public function empower()
    {
        if (request()->isMethod('GET')) {
            $platformList = (new UniacidApp())->select('id', 'name', 'uniacid')->get()->toArray();
            $pluginsMealList = (new PluginsMealModel())->getPluginsMealList();

            return $this->successJson('成功', ['platformList' => $platformList, 'pluginsMealList' => $pluginsMealList]);
        }
        if (request()->isMethod('POST')) {
            $uniacid = request()->input('uniacid');
            $plugins_meal_id = request()->input('plugins_meal_id');

            $power_type = request()->input('power_type') ?:'';

            if ($power_type == 'add' && PluginsMealPlatform::where('uniacid',$uniacid)->first())
            {
                return $this->errorJson('该平台已有使用套餐,重新授权即可无需新增');
            }

            if (!$uniacid or !$plugins_meal_id) {
                return $this->errorJson('参数错误');
            }
            $pluginsList = Option::where('uniacid', $uniacid)->pluck('option_name')->toArray();

            Menu::flush();
            \Setting::$uniqueAccountId = $uniacid;
            \YunShop::app()->uniacid = $uniacid;

            $pluginsMeal = PluginsMealModel::getPluginsMealList($plugins_meal_id);
            $pluginManager = app('app\common\services\PluginManager');
            $plugins = array_keys(app('plugins')->getPlugins()->toArray());
            PluginsMealModel::enableMeal($uniacid, $pluginsList, $pluginsMeal[0], $pluginManager, $plugins);

            if ($power_type == 'add')
            {
                (new pluginsMealPlatform())->create([
                    'uniacid' => $uniacid,
                    'plugins_meal_id' => $plugins_meal_id
                ]);
            } elseif ($power_type == "reset" ) {
                pluginsMealPlatform::where('uniacid', $uniacid)->update(['plugins_meal_id' => $plugins_meal_id]);
            }

            \Artisan::call('config:cache');
            \Cache::flush();
            return $this->successJson('套餐使用成功');
        }
    }

    public function getPlugins()
    {
        if (\YunShop::app()->uid == 1) {
            $plugin = PluginsMealModel::orderBy('order_by', 'desc')->paginate();;
        } else {
            $plugin = PluginsMealModel::where('state', 1)->orderBy('order_by', 'desc')->paginate();;
        }
        return $this->successJson('ok', $plugin);
    }

    public function record()
    {
        $data = pluginsMealPlatform::with([
            'hasOnePluginsMeal' => function ($query) {
                $query->select('id', 'name', 'plugins');
            },
            'hasOneUniacidApp' => function ($query) {
                $query->select('id', 'uniacid', 'name');
            }
        ])->orderBy('created_at', 'desc')->paginate()->toArray();

        foreach ($data['data'] as &$item) {
            $item['has_one_plugins_meal']['count_plugins'] = $item['has_one_plugins_meal']['plugins'] ? count(explode(',', $item['has_one_plugins_meal']['plugins'])) : 0;
        }
        return $this->successJson('ok', $data);
    }

    public function editMeal()
    {
        if (request()->isMethod("POST")) {
            $form = request()->input('form');
            $pluginsMealModel = PluginsMealModel::find($form['id']);
            if (!$pluginsMealModel) {
                return $this->errorJson('该套餐不存在');
            }
            $form['plugins'] = implode(',', $form['plugins']);
            $pluginsMealModel->fill($form);
            if ($pluginsMealModel->save()) {
                return $this->successJson('套餐修改成功');
            } else {
                return $this->errorJson('套餐修改失败');
            }
        }

        $plugins_meal_id = request()->input('plugins_meal_id');
        $data = PluginsMealModel::where('id', $plugins_meal_id)->first();
        if (!$data) {
            return $this->errorJson('该套餐不存在');
        }
        $data = $data->toArray();
        $data['plugins'] = explode(',', $data['plugins']);

        return $this->successJson('ok', $data);
    }

    public function delMeal()
    {
        $plugins_meal_id = request()->input('plugins_meal_id');
        $pluginsMealModel = PluginsMealModel::find($plugins_meal_id);

        if ($pluginsMealModel->delete()) {
            return $this->successJson('套餐删除成功');
        } else {
            return $this->errorJson('套餐删除失败');
        }
    }

    public function changeSate()
    {
        $data = request()->input();
        if ($data['state'] == 1 || $data['state'] == 0) {
            $plugin = PluginsMealModel::where('id', $data['id'])->update(['state' => $data['state']]);
            if ($plugin) {
                return $this->successJson('修改套餐显示状态成功');
            }
            return $this->errorJson('修改套餐显示失败，套餐可能已被删除');
        }
        return $this->errorJson('参数错误');
    }


    /**
     * 获取所有源插件
     * @return mixed|null
     */
    private function getSourcePluginList()
    {
        //增加缓存
        if (Cache::has('platform-plugins-market-list')) {
            return Cache::get('platform-plugins-market-list');
        }

		$json_content = AuthCodeService::requestUrl('plugin.json/domain');

        Cache::put('platform-plugins-market-list', $json_content, 60 * 5);
        return $json_content;
    }
}