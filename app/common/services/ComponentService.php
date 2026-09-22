<?php

namespace app\common\services;

class ComponentService
{

    /**
     * 插件的组件数据
     * @var string[]
     */
    protected static $componentData = [
        'U_title',                        //标题栏组件
        'U_slideshow',                    //轮播图组件
        'U_goods',                        //商品列表
        'U_button',                        //按钮组
        'U_location',                    //定位
        'U_search',                        //搜索框
        'U_notice',                        //公告
        "U_blank",                        //辅助空白
        "U_line",                        //辅助线
        "U_richtext",                    //富文本
        "U_cube",                        //图片组合
        "U_suspendbutton",                //悬浮按钮
        "U_maps",                        //地图
        "U_coupons",                    //优惠券
        "U_simplegraph",                //单图
        "U_video",                        //视频
        "U_membertop",                    //会员中心
        "U_memberrights",                //资产权益
        "U_membermarket",                //营销互动
        "U_membermerchant",                //商家管理
        "U_backup",                        //回到顶部
        "U_memberasset",                //资产数据
        "U_goodsnearby",                //附近商品
        // "U_headline",					//头条
        "U_shopfor",                    //店招
        "U_memberorder",                //我的订单
        "U_membertool",                    //实用工具
        "U_tabcontrol",                    //选项卡
        "U_signin",                        //签到
        "U_goodsrush",                    //限时抢购
        "U_cpssearch",          //聚合CPS搜索
        "U_goodscps",          //聚合CPS商品分组
        "U_goodstores",          //多门店核销商品
        //"U_bonuspool",          //奖金池
        "U_integral",         //积分
        "U_newNotice",        //新公告栏
        "U_membershipLevel",        //会员等级
    ];

    /**
     * 插件名称
     * @var string[]
     */
    protected static $plugin_name = [
        'fight-groups-lottery',
        'aggregation-cps',
        'love',
        'love-price-display',
        'integral',
        'distribution-coupon',
        'points-price-display',
        'present-project',
        'concession-ratio',
        'love-bonus-pool',
        'article-donate-point',
        'translation-component',
    ];

    //todo 该方法可以更改为 插件自动注入，此处实现自动加载（减少冗杂代码，每个插件相关代码放入对应插件）
    protected static function getPluginsComponent()
    {
        return [
            //
            'headline' => [
                'plugins_name' => 'article',
                'component_name' => 'U_headline'
            ],
            //文章
            'article' => [
                'plugins_name' => 'article',
                'component_name' => 'U_article'
            ],
            //门店
            'stores' => [
                'plugins_name' => 'store-cashier',
                'component_name' => 'U_stores'
            ],
            //带货直播
            'livestreaming' => [
                'plugins_name' => 'room',
                'component_name' => 'U_livestreaming'
            ],
            //微社区
            'community' => [
                'plugins_name' => 'micro-communities',
                'component_name' => 'U_community'
            ],
            //幸运大转盘
            'lottery' => [
                'plugins_name' => 'lucky-draw',
                'component_name' => 'U_lottery'
            ],
            //短视频
            'shortvideo' => [
                'plugins_name' => 'video-share',
                'component_name' => 'U_shortvideo'
            ],
            //表单
            'form' => [
                'plugins_name' => 'diyform',
                'component_name' => 'U_form'
            ],
            //拼团
            'goodsgroup' => [
                'plugins_name' => 'fight-groups',
                'component_name' => 'U_goodsgroup'
            ],
            //星拼团
            'stargroup' => [
                'plugins_name' => 'star-spell',
                'component_name' => 'U_stargroup'
            ],
            //数据显示=>慈善基金
            'memberdata' => [
                'plugins_name' => 'charity-fund',
                'component_name' => 'U_memberdata'
            ],
            //门店排名
            'storesranking' => [
                'plugins_name' => 'store-cashier',
                'component_name' => 'U_storesranking'
            ],
            //门店数据显示
            'homedata' => [
                'plugins_name' => 'store-cashier',
                'component_name' => 'U_homedata'
            ],
            //芸客服客服数据显示
            'staff' => [
                'plugins_name' => 'yun-chat',
                'component_name' => 'U_staff'
            ],
            //订单阶梯团
            'deposit' => [
                'plugins_name' => 'deposit-ladder',
                'component_name' => 'U_deposit'
            ],
            //自提点
            'selfpick' => [
                'plugins_name' => 'package-deliver',
                'component_name' => 'U_selfpick'
            ],
            //益生系统
            'ys_system' => [
                'plugins_name' => 'ys-system',
                'component_name' => 'U_memberYs'
            ],
            //奖金池
            'bounspool' => [
                'plugins_name' => 'xzhh-bonus-pool',
                'component_name' => 'U_bonuspool'
            ],
            //律师平台
            'lawyer' => [
                'plugins_name' => 'lawyer-platform',
                'component_name' => 'U_lawyer',
                'is_open' => 'Yunshop\LawyerPlatform\common\service\SetService'
            ],
            //todo is_open 对应的类应该实现某一接口，保证严谨、完整性
            //多级选项卡
            'moretabcontrol' => [
                'plugins_name' => 'moretabcontrol',
                'component_name' => 'U_moretabcontrol',
                'is_open' => '\Yunshop\Moretabcontrol\models\SetModel'
            ],
            //营销码
            'wechatcode' => [
                'plugins_name' => 'marketing-qr',
                'component_name' => 'U_wechatcode',
                'is_open' => '\Yunshop\MarketingQr\common\models\SetModel'
            ],
            //以图扫图
            'scan_picture' => [
                'plugins_name' => 'scan-picture',
                'component_name' => 'U_scanpicture',
                'is_open' => '\Yunshop\ScanPicture\common\services\DecorateService'
            ],
            'coupon_store' => [
                'plugins_name' => 'coupon-store',
                'component_name' => 'U_couponStore',
                'is_open' => 'Yunshop\CouponStore\services\SettingService',
            ],
            'web_design' => [
                'plugins_name' => 'web-design',
                'component_name' => 'U_ webDesign',
            ],
            //tg-decorate 底部炫富按钮
            'bottom_suspend_button' => [
                'plugins_name' => 'tg-decorate',
                'component_name' => 'U_bottomSuspendButton',
            ],
            //新拼团
            'ywmgroup' => [
                'plugins_name' => 'ywm-fight-groups',
                'component_name' => 'U_ywmgroup',
            ],
            //新盲盒
            'new_blind_box' => [
                'plugins_name' => 'new-blind-box',
                'component_name' => 'U_newBlindBox',
                'is_open' => '\Yunshop\NewBlindBox\services\SetService',
            ],
            'task_package' => [
                'plugins_name' => 'task-package',
                'component_name' => 'U_taskPackage',
            ],
            'course_supply' => [
                'plugins_name' => 'course-supply',
                'component_name' => 'U_courseSupply',
            ],
            //收入提现数据（区域外奖励插件）
            'region_external_reward' => [
                'plugins_name' => 'region-external-reward',
                'component_name' => 'U_regionExternalReward',
            ],
            'tiktok_cps' => [
                'plugins_name'   => 'tiktok-cps',
                'component_name' => 'U_tiktokCps',
            ],
            'yz_supply_cake' => [
                'plugins_name'   => 'yz-supply-cake',
                'component_name' => 'U_yzSupplyCake',
            ],
            'yz_supply_camilo_resources' => [
                'plugins_name'   => 'yz-supply-camilo-resources',
                'component_name' => 'U_yzSupplyCamiloResources',
            ],
            'travel_around' => [
                'plugins_name'   => 'travel-around',
                'component_name' => 'U_travelAround',
            ],
            'union_cps' => [
                'plugins_name'   => 'union-cps',
                'component_name' => 'U_unionCps',
            ],
            'yz_supply_product_album' => [
                'plugins_name'   => 'yz-supply',
                'component_name' => 'U_productAlbum',
            ],
            'horizon' => [
                'plugins_name' => 'horizon',
                'component_name' => 'U_horizon',
                'is_open' => '\Yunshop\Horizon\services\DecorateService'
            ],
            'business_card' => [
                'plugins_name' => 'business-card',
                'component_name' => 'U_businessCard',
                'is_open' => '\Yunshop\BusinessCard\common\services\CommonService'
            ],
            'goods_package' => [
                'plugins_name' => 'goods-package',
                'component_name' => 'U_goodsPackage',
            ],
            'map_fixed_point' => [
                'plugins_name' => 'map-fixed-point',
                'component_name' => 'U_mapMarker',
            ],
            'agent_shop' => [
                'plugins_name' => 'agent-shop',
                'component_name' => 'U_agentShop',
                'is_open' => '\Yunshop\AgentShop\services\DecorateService'
            ],
            'flash_sale_coupon' => [
                'plugins_name' => 'new-coupon',
                'component_name' => 'U_flashSaleCoupon',
            ],
            'new_auction' => [
                'plugins_name' => 'new-auction',
                'component_name' => 'U_newAuction',
                'is_open' => '\Yunshop\NewAuction\services\DecorateService'
            ],
            //助力比赛
            'assist_competition' => [
                'plugins_name' => 'assist-competition',
                'component_name' => 'U_assistCompetition',
            ],
            //助力比赛选项卡
            'assist_competition_vote' => [
                'plugins_name' => 'assist-competition',
                'component_name' => 'U_assistCompetitionVote',
            ],
 'compute_number' => [
                'plugins_name' => 'compute-number',
                'component_name' => 'U_calcVal',
            ],


 /** 云店 */
            'cloud_shop_zone' => [
                'plugins_name' => 'cloud-shop',
                'component_name' => 'U_NComerZone',
            ],
            'cloud_shop_up' => [
                'plugins_name' => 'cloud-shop',
                'component_name' => 'U_NComerUpgrade',
            ],
            'cloud_shop_brand' => [
                'plugins_name' => 'cloud-shop',
                'component_name' => 'U_cloudShopBrand',
            ],
            /** 云店 */        ];
    }

    /**
     * 获取有效的组件
     */
    protected static function getValidComponent()
    {
        $pluginsComponent = self::getPluginsComponent();
        $componentData = static::$componentData;

        foreach ($pluginsComponent as $key => $value) {
            if (app('plugins')->isEnabled($value['plugins_name'])) {
                $is_open = true;
                if ($value['is_open']) {
                    $myclass = new \ReflectionClass($value['is_open']); //获取组件对应的类
                    $myclass = $myclass->newInstance();
                    $is_open = $myclass->is_open();
                }

                if ($is_open === true) {
                    $componentData[] = $value['component_name'];
                }
            }
        }
        return $componentData;
    }

    /**
     * 获取开启的插件
     */
    protected static function getOpenPlugin()
    {
        $plugin_name = static::$plugin_name;
        //获取设置页面有开关的插件
        $has_open = \app\common\modules\shop\ShopConfig::current()->get('plugin.has_open');
        foreach ($plugin_name as $key => $value) {
            if (!app('plugins')->isEnabled($value)) {
                unset($plugin_name[$key]);
            }

            //判断插件设置页面是否设置开启
            if ($plugin_name[$key] && isset($has_open[$value]) && !$has_open[$value]['is_open']) {
                unset($plugin_name[$key]);
            }
        }

        return array_values($plugin_name);
    }


    protected static function getPluginLink()
    {
        return [
            static::shopPage(),
            static::memberCenter(),
            static::otherLink(),
        ];
    }

    /**
     * 商城页面
     * @return array
     */
    protected static function shopPage()
    {
        $name = '商城页面';
        $data = [
            ['name' => '商城首页', 'mini_url' => '/packageG/index/index', 'url' => 'home'],
            ['name' => '分类导航', 'mini_url' => '/packageG/pages/category_v2/category_v2', 'url' => 'category'],
            ['name' => '全部商品', 'mini_url' => '/packageB/member/category/search_v2/search_v2', 'url' => 'search'],
            ['name' => '门店聚合页', 'mini_url' => '/packageC/o2o/o2oHome/o2oHome', 'url' => 'o2o/home', 'plugin_name' => 'store-cashier'],
        ];
        $data = static::delNotOpenPlugin($data);
        return [
            'name' => $name,
            'data' => $data
        ];
    }

    /**
     * 会员中心
     * @return array
     */
    protected static function memberCenter()
    {
        $name = '会员中心';
        $data = [
            ['name' => '会员中心', 'mini_url' => '/packageG/member_v2/member_v2', 'url' => 'member'],
            ['name' => '我的订单', 'mini_url' => '/packageA/member/myOrder_v2/myOrder_v2', 'url' => 'member/orderList/0'],
            ['name' => '购物车', 'mini_url' => '/packageG/pages/buy/cart_v2/cart_v2', 'url' => 'cart'],
            ['name' => '我的收藏', 'mini_url' => '/packageD/member/collection/collection', 'url' => 'member/collection'],
            ['name' => '我的足迹', 'mini_url' => '/packageD/member/footprint/footprint', 'url' => 'member/footprint'],
            ['name' => '我的优惠券', 'mini_url' => '/packageA/member/coupon_v2/coupon_v2', 'url' => 'coupon/coupon_index'],
            ['name' => '会员充值', 'mini_url' => '/packageA/member/balance/balance/balance', 'url' => 'member/balance'],
            ['name' => '余额明细', 'mini_url' => '/packageA/member/balance/detailed/detailed', 'url' => 'member/detailed'],
        ];
        return [
            'name' => $name,
            'data' => $data
        ];
    }

    /**
     * 其他链接
     * @return array
     */
    protected static function otherLink()
    {
        $name = '其他链接';
        //如果是插件请加上plugin_name
        $data = [
            ['name' => '会员信息', 'mini_url' => '/packageA/member/info/info', 'url' => 'member/info', 'plugin_name' => ''],
            ['name' => '积分', 'mini_url' => '/packageB/member/integral/integral', 'url' => 'member/integral_v2'],
            ['name' => '收入提现', 'mini_url' => '/packageA/member/withdrawal/withdrawal', 'url' => 'member/withdrawal'],
            ['name' => '收入明细', 'mini_url' => '/packageA/member/extension/incomedetails/incomedetails', 'url' => 'member/incomedetails'],
            ['name' => '收货地址', 'mini_url' => '/packageD/member/addressList/addressList', 'url' => 'member/address'],
            ['name' => '添加收货地址', 'mini_url' => '/packageD/member/addressAdd_v2/addressAdd_v2', 'url' => 'member/appendAddress'],
            ['name' => '我的客户', 'mini_url' => '/packageD/member/myRelationship/myRelationship', 'url' => 'member/myrelationship'],
            ['name' => '我的评价', 'mini_url' => '/packageD/member/myEvaluation/myEvaluation', 'url' => 'member/myEvaluation'],
            ['name' => '推广中心', 'mini_url' => '/packageG/pages/member/extension/extension', 'url' => 'member/extension'],
            ['name' => '售后列表', 'mini_url' => '/packageD/member/myOrder/Aftersaleslist/Aftersaleslist', 'url' => 'member/aftersaleslist'],
            ['name' => '领券中心', 'mini_url' => '/packageD/coupon/coupon_store', 'url' => 'coupon/coupon_store'],
            ['name' => '搜索', 'mini_url' => '/packageB/member/category/search_v2/search_v2', 'url' => 'search'],
            ['name' => '品牌列表', 'mini_url' => '/packageB/member/category/brand_v2/brand_v2', 'url' => 'brand'],
            ['name' => '【新】我的客户', 'mini_url' => '/packageF/others/customerCenter/customerCenterIndex/customerCenterIndex', 'url' => 'customerCenterIndex', 'plugin_name' => 'customer-center'],
            ['name' => '微社区', 'mini_url' => '/packageC/micro_communities/microIndex/microIndex', 'url' => 'microHome/microIndex', 'plugin_name' => 'micro-communities'],
            ['name' => '分销商', 'mini_url' => miniVersionCompare('1.1.148')?'/mircoApp/commission/distribution/index':'/packageA/member/distribution/distribution', 'url' => 'extension/distribution', 'plugin_name' => 'commission'],
            ['name' => '名片中心', 'mini_url' => '/packageB/member/business_card/CardCenter/CardCenter', 'url' => 'business_card/card_center', 'plugin_name' => 'business-card'],
            ['name' => '签到', 'mini_url' => '/packageA/member/sign/sign', 'url' => 'member/sign', 'plugin_name' => 'sign'],
            ['name' => '排行榜', 'mini_url' => miniVersionCompare('1.1.148')?'/mircoApp/ranking/Rankings/Rankings':'/packageE/Rankings/Rankings', 'url' => 'Rankings', 'plugin_name' => 'ranking'],
            ['name' => '发现视频', 'mini_url' => '/packageC/video_goods/VideoList/VideoList', 'url' => 'videoList', 'plugin_name' => 'video-share'],
            ['name' => '音频文章', 'mini_url' => '/packageA/member/course/VoiceList/VoiceList', 'url' => 'voiceList', 'plugin_name' => 'article'],
            ['name' => '带货直播列表', 'mini_url' => '/packageD/directSeeding/liveList/liveList', 'url' => 'liveList', 'plugin_name' => 'room'],
            ['name' => '拼团列表', 'mini_url' => '/packageB/member/group/GroupList/GroupList', 'url' => 'group_list', 'plugin_name' => 'fight-groups'],
            ['name' => '我的拼团', 'mini_url' => '/packageB/member/group/MyGroups/MyGroups', 'url' => 'mygroups', 'plugin_name' => 'fight-groups'],
        ];

        $data = static::delNotOpenPlugin($data);

        return [
            'name' => $name,
            'data' => $data
        ];
    }

    /**
     * 处理插件没开的链接
     * @param array $pluginList
     * @return array
     */
    protected static function delNotOpenPlugin(array $pluginList): array
    {
        foreach ($pluginList as $pluginKey => &$plugin) {

            //没开启的插件就不用显示了
            if (isset($plugin['plugin_name'])) {
                if (!app('plugins')->isEnabled($plugin['plugin_name'])) {
                    unset($pluginList[$pluginKey]);
                }
                unset($pluginList[$pluginKey]['plugin_name']);
            }
            //完整url
            $plugin['path_url'] = isset($plugin['url']) ? yzAppFullUrl($plugin['url']) : null;
        }
        $pluginList = array_values($pluginList);
        return $pluginList;
    }

    public static function getComponentList()
    {
        $data = [];
        $data['componentList'] = static::getValidComponent();
        $data['open_plugin'] = static::getOpenPlugin();
        $data['plugin_link'] = static::getPluginLink();

        if (!is_null($event_arr = \app\common\modules\shop\ShopConfig::current()->get('decorate_get_component_list_plugin_data'))) {
            foreach ($event_arr as $key => $event) {
                $class = array_get($event, 'class');
                $function = array_get($event, 'function');
                $res = $class::$function();
                if ($res) {
                    $data['plugin_data'][$key] = $res;
                }
            }
        }

        return $data;
    }
}
