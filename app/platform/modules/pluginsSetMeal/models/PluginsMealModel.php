<?php


namespace app\platform\modules\pluginsSetMeal\models;

use app\common\models\BaseModel;

class PluginsMealModel extends BaseModel
{
    protected $table = 'yz_plugins_meal';
    protected $fillable = ['order_by', 'name', 'plugins', 'state'];


    // 获取插件套餐数据，并返回套餐插件的数量
    public static function getPluginsMealList($id = null)
    {
        $list = self::select('id', 'name', 'plugins', 'order_by', 'state');

        if ($id) {
            $list = $list->where('id', $id);
        }

        $list = $list->orderBy('order_by', 'DESC')
            ->get()
            ->toArray();
        foreach ($list as &$item) {
            $item['plugins'] = explode(',', $item['plugins']);
            $item['count'] = count($item['plugins']);
        }
        return $list;
    }

    public static function enableMeal($uniacid, $pluginsList, $pluginsMeal, $pluginManager,
                                      $plugins)
    {
        @ini_set('memory_limit', -1);
        $pluginManager->disableMeal($uniacid);//关闭所有插件
        foreach ($pluginsList as $plugin) {
            $pluginManager->dispatchEvent($plugin);
        }
        $afterPluginsList = []; //存储启动捕获到异常的插件

        foreach ($pluginsMeal['plugins'] as $key => $plugin) {
            if (in_array($plugin, $plugins)) {
                if (!$pluginManager->enableMeal($plugin)) {
                    $afterPluginsList[] = $plugin;
                }  //启用插件套餐
            }
        }

        //尝试重新执行捕获到异常的插件
        if (!empty($afterPluginsList)) {
            \Artisan::call('config:cache');  //todo 必须刷新缓存，否则无法启动
            \Cache::flush();
            foreach ($afterPluginsList as $item) {
                $pluginManager->enableMeal($item);  //启用插件套餐
            }
        }
    }
}