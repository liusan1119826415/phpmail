<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2021/9/22
 * Time: 13:37
 */

namespace business\admin\menu;

use app\common\helpers\Cache;
use business\common\models\Business;
use business\common\services\BusinessService;
use business\common\services\SettingService;

class BusinessMenu
{

    const KEY = 'business_menu';
    const PLUGIN_KEY = 'business_plugin_menu';

    public function getMenu($business_id = 0)
    {
        $business_id = $business_id ?: SettingService::getBusinessId();
        if ($memnu = Cache::get(self::KEY . '_' . $business_id, null, [BusinessService::getModuleRedisKey(\YunShop::app()->uniacid), BusinessService::getBusinessRedisKey($business_id)])) {
            return $memnu;
        }


        $route = [];
        $page_route = [];
        $page = [];
        $all_route = [];

        $plugin_menu_arr = \app\common\modules\shop\ShopConfig::current()->get(self::PLUGIN_KEY) ?: []; //获取插件路由和页面
        $plugin_menu_arr = array_merge(['admin' => [
            'class' => '\business\admin\menu\BusinessMenu',
            'function' => 'adminMenu',
        ]], $plugin_menu_arr);

        $auth_plugins = SettingService::getEnablePlugins($business_id);
        foreach ($plugin_menu_arr as $k => $v) {
            $plugin_name = SettingService::changePluginName($k);
            if ($k != 'admin' && (!app('plugins')->isEnabled($plugin_name) || !in_array($k, $auth_plugins))) {
                continue;
            }
            $class = $v['class'];
            $function = $v['function'];
            if (!method_exists($class, $function)) continue;
            if ($res = $class::$function()) {
                $all_route[$k] = $res;
                if ($this_route = $this->getRoute($res, $k)) {
                    $route[$k] = $this_route;
                }
                if ($this_page = $this->getMenuPage($res, $k)) {
                    $page[$k] = $this_page;
                }
                if ($this_page_route = $this->getPageRoute($res, $k)) {
                    $page_route[$k] = $this_page_route;
                }
            }

        }

        $menu = ['route' => $route, 'page' => $page, 'page_route' => $page_route, 'all_route' => $all_route];
        Cache::put(self::KEY, $menu, 3600, [BusinessService::getModuleRedisKey(\YunShop::app()->uniacid), BusinessService::getBusinessRedisKey($business_id)]);

        return $menu;

    }


    /*
     * 获取侧边栏页面数据
     */
    public function getMenuPage($data, $module = 'admin', &$return_data = [], &$first_level = [], &$second_level = [], &$third_level = [])
    {

        foreach ($data as $k => $v) {
            if ($v['type'] == 1) {

                /*第一层侧栏*/
                if ($v['first_tab_show']) {
                    $return_data[$k] = [
                        'route' => $v['route'],
                        'module' => $module,
                        'premit' => $v['premit'],
                        'page_name' => $v['page_name'],
                        'identity' => $v['identity'],
                        'tab_name' => is_array($v['tab_name']) ? $v['tab_name'][0] : $v['tab_name'],
                        'special_check' => $v['special_check'] ?: '',
                    ];
                    $first_level[$k] = [$k];
                }
                /*第一层侧栏*/

                /*第二层侧栏*/
                if ($v['second_tab_show']) {
                    $second_key = $v['first_tab_show'] ? $k : end($v['parent']);
                    $second_level_arr = $first_level[$second_key];
                    $second_level_arr[] = $k;
                    $second_level[$k] = $second_level_arr;
                    if (!is_array($v['tab_name'])) {
                        $tab_name = $v['tab_name'];
                    } elseif ($v['first_tab_show']) {
                        $tab_name = $v['tab_name'][1];
                    } elseif ($v['third_tab_show']) {
                        $tab_name = $v['tab_name'][0];
                    }
                    $return_data[$second_level_arr[0]]['child'][$second_level_arr[1]] = [
                        'route' => $v['route'],
                        'module' => $module,
                        'premit' => $v['premit'],
                        'identity' => $v['identity'],
                        'page_name' => $v['page_name'],
                        'tab_name' => $tab_name ?: '',
                        'special_check' => $v['special_check'] ?: '',
                    ];
                }
                /*第二层侧栏*/

                /*第三层侧栏*/
                if ($v['third_tab_show']) {
                    $third_key = $v['second_tab_show'] ? $k : end($v['parent']);
                    $third_level_arr = $second_level[$third_key];
                    $third_level_arr[] = $k;
                    $third_level[$k] = $third_level_arr;
                    $return_data[$third_level_arr[0]]['child'][$third_level_arr[1]]['child'][$third_level_arr[2]] = [
                        'route' => $v['route'],
                        'module' => $module,
                        'premit' => $v['premit'],
                        'identity' => $v['identity'],
                        'page_name' => $v['page_name'],
                        'tab_name' => is_array($v['tab_name']) ? end($v['tab_name']) : $v['tab_name'],
                        'special_check' => $v['special_check'] ?: '',
                    ];
                }
                /*第三层侧栏*/
            }

            if ($v['child']) {
                $this->getMenuPage($v['child'], $module, $return_data, $first_level, $second_level, $third_level);
            }

        }

        return $return_data;
    }


    /*
    * 获取页面路由
    */
    public function getPageRoute($data, $module = 'admin', &$return_data = [])
    {
        foreach ($data as $k => $v) {

            if ($v['type'] == 1 && $v['route']) {
                $return_data[] = [
                    'route' => $v['route'],
                    'module' => $module,
                    'can' => $v['premit'] ? 0 : 1,
                    'identity' => $v['identity'],
                ];
            }

            if ($v['child']) {
                $this->getPageRoute($v['child'], $module, $return_data);
            }

        }

        return $return_data;
    }

    /*
     * 获取路由
     */
    public function getRoute($data, $module = 'admin', &$return_data = [])
    {
        foreach ($data as $k => $v) {

            if ($v['type'] == 2) {

                $return_data[] = [
                    'route' => $v['route'],
                    'module' => $module,
                    'can' => $v['premit'] ? 0 : 1,
                    'identity' => $v['identity'],
                    'special_check' => $v['special_check'] ?: '',
                ];
            }

            if ($v['child']) {
                $this->getRoute($v['child'], $module, $return_data);
            }

        }

        return $return_data;
    }


    public static function adminMenu()
    {

        return [
            'businessUpload' => [
                'parent' => [],
                'page_name' => '上传文件', //页面或路由名称(用于权限设置页面展示)
                'tab_name' => '上传文件', //侧边栏显示名称
                'first_tab_show' => 0, //是否在侧边栏1显示
                'second_tab_show' => 0, //是否在侧边栏2显示
                'third_tab_show' => 0, //是否在侧边栏3显示
                'icon' => '', //侧边栏图标
                'type' => 2, // 1页面 2路由
                'route' => 'uploadPic', //前端页面路径标识 或 后端接口路由，根据type填写
                'premit' => 0, //是否需要路由权限验证，与身份权限验证互相独立，其中一个不满足则无法访问
                'identity' => 0,//需求身份权限 0不限制 1员工 2管理员 3创建人 4法人 5创建人+法人，与路由权限验证互相独立，其中一个不满足则无法访问
                'plugin' => '',//是否插件相关，是的话填写插件名，如team-dividend
                'child' => []
            ],
            'getArea' => [
                'parent' => [''],
                'page_name' => '获取地址',
                'tab_name' => '获取地址',
                'first_tab_show' => 0,
                'second_tab_show' => 0,
                'third_tab_show' => 0,
                'icon' => '',
                'type' => 2,
                'route' => 'getArea',
                'premit' => 0,
                'identity' => 1,
                'plugin' => '',
                'child' => [],
            ],
            'streetSet' => [
                'parent' => [''],
                'page_name' => '获取街道开关',
                'tab_name' => '获取街道开关',
                'first_tab_show' => 0,
                'second_tab_show' => 0,
                'third_tab_show' => 0,
                'icon' => '',
                'type' => 2,
                'route' => 'streetSet',
                'premit' => 0,
                'identity' => 1,
                'plugin' => '',
                'child' => [],
            ],
            'intArea' => [
                'parent' => [''],
                'page_name' => '初始化地址',
                'tab_name' => '初始化地址',
                'first_tab_show' => 0,
                'second_tab_show' => 0,
                'third_tab_show' => 0,
                'icon' => '',
                'type' => 2,
                'route' => 'intArea',
                'premit' => 0,
                'identity' => 1,
                'plugin' => '',
                'child' => [],
            ],
            'businessAddressList' => [
                'parent' => [],
                'page_name' => '地址列表', //页面或路由名称(用于权限设置页面展示)
                'tab_name' => '地址列表', //侧边栏显示名称
                'first_tab_show' => 0, //是否在侧边栏1显示
                'second_tab_show' => 0, //是否在侧边栏2显示
                'third_tab_show' => 0, //是否在侧边栏3显示
                'icon' => '', //侧边栏图标
                'type' => 2, // 1页面 2路由
                'route' => 'getAddressList', //前端页面路径标识 或 后端接口路由，根据type填写
                'premit' => 0, //是否需要路由权限验证，与身份权限验证互相独立，其中一个不满足则无法访问
                'identity' => 0,//需求身份权限 0不限制 1员工 2管理员 3创建人 4法人 5创建人+法人，与路由权限验证互相独立，其中一个不满足则无法访问
                'plugin' => '',//是否插件相关，是的话填写插件名，如team-dividend
                'child' => []
            ],
            'businessListPage' => [  //
                'parent' => [],
                'page_name' => '企业管理页面', //页面或路由名称(用于权限设置页面展示)
                'tab_name' => '企业管理', //侧边栏显示名称
                'first_tab_show' => 0, //是否在侧边栏1显示
                'second_tab_show' => 0, //是否在侧边栏2显示
                'third_tab_show' => 0, //是否在侧边栏3显示
                'icon' => '', //侧边栏图标
                'type' => 1, // 1页面 2路由
                'route' => '', //前端页面路径标识 或 后端接口路由，根据type填写
                'premit' => 0, //是否需要路由权限验证，与身份权限验证互相独立，其中一个不满足则无法访问
                'identity' => 0,//需求身份权限 0不限制 1员工 2管理员 3创建人 4法人 5创建人+法人，与路由权限验证互相独立，其中一个不满足则无法访问
                'plugin' => '',//是否插件相关，是的话填写插件名，如team-dividend
                'child' => [
                    'managerList' => [ //管理员列表页面
                        'parent' => ['businessListPage'],
                        'page_name' => '管理员列表',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'managerList',
                        'premit' => 0,
                        'identity' => 0,
                        'plugin' => '',
                        'child' => []
                    ],
                    'changeBusinessOwner' => [ //管理员列表页面
                        'parent' => ['businessListPage'],
                        'page_name' => '企业转让',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'changeBusinessOwner',
                        'premit' => 0,
                        'identity' => 0,
                        'plugin' => '',
                        'child' => []
                    ],
                    'addManager' => [ //管理员列表页面
                        'parent' => ['businessListPage'],
                        'page_name' => '添加管理员',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'addManager',
                        'premit' => 0,
                        'identity' => 0,
                        'plugin' => '',
                        'child' => []
                    ],
                    'deleteManager' => [ //管理员列表页面
                        'parent' => ['businessListPage'],
                        'page_name' => '删除管理员',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'deleteManager',
                        'premit' => 0,
                        'identity' => 0,
                        'plugin' => '',
                        'child' => []
                    ],
                    'getBusinessCommonData' => [ //公共参数接口
                        'parent' => ['businessListPage'],
                        'page_name' => '获取公共参数',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'getBusinessCommonData',
                        'premit' => 0,
                        'identity' => 0,
                        'plugin' => '',
                        'child' => []
                    ],
                    'businessList' => [ //页面列表接口
                        'parent' => ['businessListPage'],
                        'page_name' => '获取企业列表',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'businessList',
                        'premit' => 0,
                        'identity' => 0,
                        'plugin' => '',
                        'child' => []
                    ],
                    'addBussiness' => [ //创建企业
                        'parent' => ['business_list_page'],
                        'page_name' => '创建企业',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'addBussiness',
                        'premit' => 0,
                        'identity' => 0,
                        'plugin' => '',
                        'child' => []
                    ],
                    'manageBusiness' => [ //设置管理的企业
                        'parent' => ['business_list_page'],
                        'page_name' => '管理企业',
                        'tab_name' => '', //侧栏名称
                        'first_tab_show' => 0, //是否在侧边栏1显示
                        'second_tab_show' => 0, //是否在侧边栏2显示
                        'third_tab_show' => 0, //是否在侧边栏3显示
                        'icon' => '', //侧边栏图标
                        'type' => 2, // 1页面 2路由
                        'route' => 'manageBusiness', //页面路径 或 路由
                        'premit' => 0, //是否需要权限验证
                        'identity' => 0,
                        'plugin' => '',//是否插件相关，是的话填写插件名，如team-dividend
                        'child' => []
                    ],
                ]
            ],
            'businessSurvey' => [ //概况页面
                'parent' => [],
                'page_name' => '概况', //页面名称
                'tab_name' => '概况', //侧栏名称
                'first_tab_show' => 0, //是否在侧边栏1显示
                'second_tab_show' => 0, //是否在侧边栏2显示
                'third_tab_show' => 0, //是否在侧边栏3显示
                'icon' => '', //侧边栏图标
                'type' => 1, // 1页面 2路由
                'route' => 'overview', //页面路径 或 路由
                'premit' => 0, //是否需要权限验证
                'identity' => 0,
                'plugin' => '',//是否插件相关，是的话填写插件名，如team-dividend
                'child' => [
                    'cleanMemberCache' => [ //清除会员缓存
                        'parent' => ['businessSurvey'],
                        'page_name' => '清除会员缓存',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'cleanMemberCache',
                        'premit' => 0,
                        'identity' => 0,
                        'plugin' => '',
                        'child' => []
                    ],
                    'cleanBusinessCache' => [ //清除企业缓存
                        'parent' => ['businessSurvey'],
                        'page_name' => '清除企业缓存',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'cleanMemberCache',
                        'premit' => 0,
                        'identity' => 2,
                        'plugin' => '',
                        'child' => []
                    ],
                    'getBusinessSurvey' => [ //获取企业概况
                        'parent' => ['businessSet'],
                        'page_name' => '企业概况',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'getBusinessSurvey',
                        'premit' => 0,
                        'identity' => 1,
                        'plugin' => '',
                        'child' => []
                    ],
                    'businessGetMemberByMobile' => [ //根据手机号精确查找会员
                        'parent' => ['businessSet'],
                        'page_name' => '根据手机号精确查找会员',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'businessGetMemberByMobile',
                        'premit' => 0,
                        'identity' => 1,
                        'plugin' => '',
                        'child' => []
                    ],
                    'searchStaff' => [ //查找企业员工
                        'parent' => ['businessSet'],
                        'page_name' => '查找企业员工',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'searchStaff',
                        'premit' => 0,
                        'identity' => 1,
                        'plugin' => '',
                        'child' => []
                    ],
                ],
            ],
            'businessMessageNotice' => [ //概况页面
                'page_name' => '消息管理', //页面名称
                'tab_name' => '消息管理', //侧栏名称
                'first_tab_show' => 1, //是否在侧边栏1显示
                'second_tab_show' => 0, //是否在侧边栏2显示
                'third_tab_show' => 0, //是否在侧边栏3显示
                'icon' => '', //侧边栏图标
                'type' => 1, // 1页面 2路由
                'route' => 'worktileMsg', //页面路径 或 路由
                'premit' => 0, //是否需要权限验证
                'identity' => 1,
                'plugin' => '',//是否插件相关，是的话填写插件名，如team-dividend
                'child' => [
                    'businessMessageNoticeWorktileMsgInd' => [
                        'parent' => ['businessMessageNotice'],
                        'page_name' => '通知',
                        'tab_name' => '通知',
                        'first_tab_show' => 0,
                        'second_tab_show' => 1,
                        'third_tab_show' => 0,
                        'type' => 1,
                        'route' => 'worktileMsgInd',
                        'premit' => 0,
                        'identity' => 1,
                        'plugin' => '',
                        'child' => [],
                    ],
                    'businessMessageUnread' => [
                        'parent' => ['businessMessageNotice'],
                        'page_name' => '未读消息列表',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'message/unread',
                        'premit' => 0,
                        'identity' => 0,
                        'plugin' => '',
                        'child' => []
                    ],
                    'businessMessageRead' => [
                        'parent' => ['businessMessageNotice'],
                        'page_name' => '已读消息列表',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'message/read',
                        'premit' => 0,
                        'identity' => 0,
                        'plugin' => '',
                        'child' => []
                    ],
                    'businessMessageWaitHandle' => [
                        'parent' => ['businessMessageNotice'],
                        'page_name' => '待处理消息列表',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'message/waitHandle',
                        'premit' => 0,
                        'identity' => 0,
                        'plugin' => '',
                        'child' => []
                    ],
                    'businessMessageMarkRead' => [
                        'parent' => ['businessMessageNotice'],
                        'page_name' => '确认已读',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'message/markRead',
                        'premit' => 0,
                        'identity' => 0,
                        'plugin' => '',
                        'child' => []
                    ],
                    'businessMessageBatchMarkRead' => [
                        'parent' => ['businessMessageNotice'],
                        'page_name' => '确认全部已读',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'message/batchMarkRead',
                        'premit' => 0,
                        'identity' => 0,
                        'plugin' => '',
                        'child' => []
                    ],
                    'businessMessageAlreadyHandle' => [
                        'parent' => ['businessMessageNotice'],
                        'page_name' => '确认已处理',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'message/alreadyHandle',
                        'premit' => 0,
                        'identity' => 0,
                        'plugin' => '',
                        'child' => []
                    ],
                    'businessMessageLaterHandle' => [
                        'parent' => ['businessMessageNotice'],
                        'page_name' => '加入待处理',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'message/laterHandle',
                        'premit' => 0,
                        'identity' => 0,
                        'plugin' => '',
                        'child' => []
                    ],
                    'businessMessageAllAppModule' => [
                        'parent' => ['businessMessageNotice'],
                        'page_name' => '消息应用模块',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'message/allAppModule',
                        'premit' => 0,
                        'identity' => 0,
                        'plugin' => '',
                        'child' => []
                    ],

                ],
            ],
            'businessSet' => [ //设置页面
                'parent' => [],
                'page_name' => '企业设置',
                'tab_name' => ['企业设置', '企业信息'],
                'first_tab_show' => 1,
                'second_tab_show' => 1,
                'third_tab_show' => 0,
                'icon' => '',
                'type' => 1,
                'route' => 'setting',
                'premit' => 1,
                'identity' => 1,
                'plugin' => '',
                'child' => [
                    'editBussiness' => [ //编辑企业信息
                        'parent' => ['businessSet'],
                        'page_name' => '企业信息',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'editBussiness',
                        'premit' => 1,
                        'identity' => 1,
                        'plugin' => '',
                        'child' => []
                    ],
                    'businessSetQyInformation' => [ //企业微信设置页面
                        'parent' => ['businessSet'],
                        'page_name' => '查看企业微信设置',
                        'tab_name' => '企业微信设置',
                        'first_tab_show' => 0,
                        'second_tab_show' => 1,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 1,
                        'route' => 'settingConfig',
                        'premit' => 1,
                        'identity' => 1,
                        'plugin' => '',
                        'child' => [
                            'businessQyWxSetting' => [ //企业微信设置
                                'parent' => ['businessSet', 'businessSetInformation'],
                                'page_name' => '查看/编辑企业微信设置',
                                'tab_name' => '',
                                'first_tab_show' => 0,
                                'second_tab_show' => 0,
                                'third_tab_show' => 0,
                                'icon' => '',
                                'type' => 2,
                                'route' => 'businessQyWxSetting',
                                'premit' => 1,
                                'identity' => 1,
                                'plugin' => '',
                                'child' => []
                            ]
                        ],
                    ]
                ],
            ],
            'businessDepartment' => [ //部门管理
                'parent' => [],
                'page_name' => '部门管理',
                'tab_name' => ['部门管理', '部门管理'],
                'first_tab_show' => 1,
                'second_tab_show' => 1,
                'third_tab_show' => 0,
                'icon' => '',
                'type' => 1,
                'route' => 'department',
                'premit' => 1,
                'identity' => 1,
                'plugin' => '',
                'child' => [
                    'refreshDepartmentList' => [ //企业微信部门同步
                        'parent' => ['businessDepartment'],
                        'page_name' => '企业微信部门同步',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'refreshDepartmentList',
                        'premit' => 1,
                        'identity' => 1,
                        'plugin' => '',
                        'child' => [],
                    ],
                    'getDepatmemtList' => [ //获取部门列表
                        'parent' => ['businessDepartment'],
                        'page_name' => '获取部门列表',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'getDepatmemtList',
                        'premit' => 1,
                        'identity' => 1,
                        'plugin' => '',
                        'child' => [],
                    ],
                    'createDepartment' => [
                        'parent' => ['businessDepartment'],
                        'page_name' => '创建部门',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'createDepartment',
                        'premit' => 0,
                        'identity' => 1,
                        'plugin' => '',
                        'child' => [],
                    ],
                    'createAllDepartment' => [
                        'parent' => ['businessDepartment'],
                        'page_name' => '全部门管理(创建部门)',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'createAllDepartment',
                        'premit' => 1,
                        'identity' => 1,
                        'plugin' => '',
                        'child' => [],
                    ],
                    'createSubDepartment' => [
                        'parent' => ['businessDepartment'],
                        'page_name' => '子部门管理(创建部门)',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'createSubDepartment',
                        'premit' => 1,
                        'identity' => 1,
                        'plugin' => '',
                        'child' => [],
                    ],
                    'updateDepartment' => [
                        'parent' => ['businessDepartment'],
                        'page_name' => '编辑部门',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'updateDepartment',
                        'premit' => 0,
                        'identity' => 1,
                        'plugin' => '',
                        'child' => [],
                    ],
                    'updateAllDepartment' => [
                        'parent' => ['businessDepartment'],
                        'page_name' => '全部门管理(编辑部门)',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'updateAllDepartment',
                        'premit' => 1,
                        'identity' => 1,
                        'plugin' => '',
                        'child' => [],
                    ],
                    'updateSubDepartment' => [
                        'parent' => ['businessDepartment'],
                        'page_name' => '子部门管理(编辑部门)',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'updateSubDepartment',
                        'premit' => 1,
                        'identity' => 1,
                        'plugin' => '',
                        'child' => [],
                    ],
                    'deleteDepartment' => [
                        'parent' => ['businessDepartment'],
                        'page_name' => '删除部门',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'deleteDepartment',
                        'premit' => 0,
                        'identity' => 1,
                        'plugin' => '',
                        'child' => [],
                    ],
                    'deleteAllDepartment' => [
                        'parent' => ['businessDepartment'],
                        'page_name' => '全部门管理(删除部门)',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'deleteAllDepartment',
                        'premit' => 1,
                        'identity' => 1,
                        'plugin' => '',
                        'child' => [],
                    ],
                    'deleteSubDepartment' => [
                        'parent' => ['businessDepartment'],
                        'page_name' => '子部门管理(删除部门)',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'deleteSubDepartment',
                        'premit' => 1,
                        'identity' => 1,
                        'plugin' => '',
                        'child' => [],
                    ],
                    'pushDepartment' => [
                        'parent' => ['businessDepartment'],
                        'page_name' => '推送部门到企业微信',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'pushDepartment',
                        'premit' => 1,
                        'identity' => 1,
                        'plugin' => '',
                        'child' => [],
                    ],
                ],
            ],
            'businessStaff' => [ //员工管理
                'parent' => [],
                'page_name' => '员工管理', //页面名称
                'tab_name' => ['员工管理', '员工管理'], //侧栏名称
                'first_tab_show' => 1, //是否在侧边栏1显示
                'second_tab_show' => 1, //是否在侧边栏2显示
                'third_tab_show' => 0, //是否在侧边栏3显示
                'icon' => '', //侧边栏图标
                'type' => 1, // 1页面 2路由
                'route' => 'staff', //页面路径 或 路由
                'premit' => 1, //是否需要权限验证
                'identity' => 1,
                'plugin' => '',//是否插件相关，是的话填写插件名，如team-dividend
                'child' => [
                    'getStaffList' => [
                        'parent' => ['businessSet', 'businessStaff'],
                        'page_name' => '获取员工列表',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'getStaffList',
                        'premit' => 1,
                        'identity' => 1,
                        'plugin' => '',
                        'child' => [],
                    ],
                    'refreshStaffList' => [
                        'parent' => ['businessSet', 'businessStaff'],
                        'page_name' => '同步企业微信员工',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'refreshStaffList',
                        'premit' => 1,
                        'identity' => 1,
                        'plugin' => '',
                        'child' => [],
                    ],
//                    'pushStaff' => [
//                        'parent' => ['businessSet', 'businessStaff'],
//                        'page_name' => '推送员工信息到企业微信',
//                        'tab_name' => '',
//                        'first_tab_show' => 0,
//                        'second_tab_show' => 0,
//                        'third_tab_show' => 0,
//                        'icon' => '',
//                        'type' => 2,
//                        'route' => 'pushStaff',
//                        'premit' => 1,
//                        'identity' => 1,
//                        'plugin' => '',
//                        'child' => [],
//                    ],
                    'setDepartmentLeader' => [
                        'parent' => ['businessSet', 'businessStaff'],
                        'page_name' => '设置部门领导',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'setDepartmentLeader',
                        'premit' => 0,
                        'identity' => 1,
                        'plugin' => '',
                        'child' => [],
                    ],
                    'createStaff' => [
                        'parent' => ['businessSet', 'businessStaff'],
                        'page_name' => '创建员工',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'createStaff',
                        'premit' => 1,
                        'identity' => 1,
                        'plugin' => '',
                        'child' => [],
                    ],
                    'updateStaff' => [
                        'parent' => ['businessSet', 'businessStaff'],
                        'page_name' => '编辑员工',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'updateStaff',
                        'premit' => 1,
                        'identity' => 1,
                        'plugin' => '',
                        'child' => [],
                    ],
                    'deleteStaff' => [
                        'parent' => ['businessSet', 'businessStaff'],
                        'page_name' => '禁用员工',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'deleteStaff',
                        'premit' => 1,
                        'identity' => 1,
                        'plugin' => '',
                        'child' => [],
                    ],
                ]
            ],
            'businessApplication' => [
                'parent' => [],
                'page_name' => '应用中心',
                'tab_name' => '应用中心',
                'first_tab_show' => 1,
                'second_tab_show' => 0,
                'third_tab_show' => 0,
                'icon' => '',
                'type' => 1,
                'route' => 'application',
                'premit' => 0,
                'identity' => 1,
                'plugin' => '',
                'child' => [
                    'getApplicationList' => [
                        'parent' => ['businessApplication'],
                        'page_name' => '应用列表',
                        'tab_name' => '应用列表',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'getApplicationList',
                        'premit' => 0,
                        'identity' => 1,
                        'plugin' => '',
                        'child' => [],
                    ],
                    'businessApplicationWork' => [
                        'parent' => ['businessApplication'],
                        'page_name' => '办公管理',
                        'tab_name' => '办公管理',
                        'first_tab_show' => 0,
                        'second_tab_show' => 1,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 1,
                        'route' => '',
                        'premit' => 0,
                        'identity' => 1,
                        'plugin' => '',
                        'child' => [],
                    ],
                    'businessApplicationSales' => [
                        'parent' => ['businessApplication'],
                        'page_name' => '销售管理',
                        'tab_name' => '销售管理',
                        'first_tab_show' => 0,
                        'second_tab_show' => 1,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 1,
                        'route' => '',
                        'premit' => 0,
                        'identity' => 1,
                        'plugin' => '',
                        'child' => [],
                    ],
                    'businessApplicationTool' => [
                        'parent' => ['businessApplication'],
                        'page_name' => '工具软件',
                        'tab_name' => '工具软件',
                        'first_tab_show' => 0,
                        'second_tab_show' => 1,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 1,
                        'route' => '',
                        'premit' => 0,
                        'identity' => 1,
                        'plugin' => '',
                        'child' => [],
                    ],
                ],
            ],
            'businessAuthPage' => [
                'parent' => [],
                'page_name' => '部门权限管理',
                'tab_name' => '部门权限管理',
                'first_tab_show' => 0,
                'second_tab_show' => 0,
                'third_tab_show' => 0,
                'icon' => '',
                'type' => 1,
                'route' => 'departmentPermission',
                'premit' => 0,
                'identity' => 2,
                'plugin' => '',
                'child' => [
                    'businessStaffAuthPage' => [
                        'parent' => ['businessAuthPage'],
                        'page_name' => '员工权限管理',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'staffPermission',
                        'premit' => 0,
                        'identity' => 2,
                        'plugin' => '',
                        'child' => [],
                    ],
                    'getAuthList' => [
                        'parent' => ['businessAuthPage'],
                        'page_name' => '权限列表',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'getAuthList',
                        'premit' => 0,
                        'identity' => 2,
                        'plugin' => '',
                        'child' => [],
                    ],
                    'setAuth' => [
                        'parent' => ['businessAuthPage'],
                        'page_name' => '设置权限',
                        'tab_name' => '',
                        'first_tab_show' => 0,
                        'second_tab_show' => 0,
                        'third_tab_show' => 0,
                        'icon' => '',
                        'type' => 2,
                        'route' => 'setAuth',
                        'premit' => 0,
                        'identity' => 2,
                        'plugin' => '',
                        'child' => [],
                    ],
                ],
            ],
//            'getDepartmentMember' => [
//                'parent' => [],
//                'page_name' => '获取所有部门和员工列表(企业微信侧边栏、电子合同使用)',
//                'tab_name' => '',
//                'first_tab_show' => 0,
//                'second_tab_show' => 0,
//                'third_tab_show' => 0,
//                'icon' => '',
//                'type' => 2,
//                'route' => 'getDepartmentMember',
//                'premit' => 1,
//                'identity' => 1,
//                'plugin' => '',
//                'child' => [],
//            ],
        ];
    }


}