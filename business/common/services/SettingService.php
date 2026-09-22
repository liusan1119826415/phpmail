<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2021/9/22
 * Time: 13:37
 */


namespace business\common\services;

use app\common\facades\Setting;
use app\common\helpers\QrCodeHelper;
use app\common\services\Session;
use business\common\models\Business;
use business\common\models\PlatLog;
use business\common\models\StaffTicketCode;
use EasyWeChat\Kernel\Support;

class SettingService
{

    const BUSINESS_PLUGIN_KEY = 'business_plugin_application';
    static $business_id;

    private const PLUGIN_LIST = [
        'group-develop-user' => ['key' => 'GroupDevelopUser', 'name' => '群拓客'],
        'wechat-chat-sidebar' => ['key' => 'WechatChatSidebar', 'name' => '聊天侧边栏'],
        'wechat-customers' => ['key' => 'WechatCustomers', 'name' => '企业客户管理'],
        'yun-chat' => ['key' => 'YunChat', 'name' => '在线客服'],
        'yun-chat-sub' => ['key' => 'YunChatSub', 'name' => '在线客服客服端'],
        'work-wechat-tag' => ['key' => 'WorkWechatTag', 'name' => '企业微信标签'],
        'customer-increase' => ['key' => 'CustomerIncrease', 'name' => '企业微信好友裂变'],
        'group-reward' => ['key' => 'GroupReward', 'name' => '群拓客活动奖励'],
        'sop-task' => ['key' => 'SopTask', 'name' => 'sop任务'],
        'speechcraft-library' => ['key' => 'SpeechcraftLibrary', 'name' => '话术库'],
        'discount-harvest-fans' => ['key' => 'DiscountHarvestFans', 'name' => '让利涨粉'],
        'shop-pos' => ['key' => 'ShopPos', 'name' => 'pos收银'],
        'customer-radar' => ['key' => 'CustomerRadar', 'name' => '拓客雷达'],
        'project-manager' => ['key' => 'ProjectManager', 'name' => '项目管理'],
        'lawyer-platform' => ['key' => 'LawyerPlatform', 'name' => '律所管理'],
        'customer-manage' => ['key' => 'CustomerManage', 'name' => '客户管理'],
        'outbound-system' => ['key' => 'OutboundSystem', 'name' => '外呼电销系统'],
        'drainage-code' => ['key' => 'DrainageCode', 'name' => '爆客码'],
        'crowdfunding' => ['key' => 'Crowdfunding', 'name' => '众筹活动-渠道端'],
        'staff-audit' => ['key' => 'StaffAudit', 'name' => '员工审批'],
        'welcome-words' => ['key' => 'WelcomeWords', 'name' => '欢迎语'],
        'opportunity-management' => ['key' => 'OpportunityManagement', 'name' => '商机管理'],
        'wechat-video-courses' => ['key' => 'WechatVideoCourses', 'name' => '企业微信视频课程'],
        'store-pos' => ['key' => 'StorePos', 'name' => '门店pos收银'],
        'assessment' => ['key' => 'Assessment', 'name' => '测评'],
//        \app\common\modules\shop\ShopConfig::current()->get('work-wechat-platform-plugin')在扩展中添加
    ];

    public static function getPluginList()
    {
        $plugin_list = SettingService::PLUGIN_LIST;
        foreach (\app\common\modules\shop\ShopConfig::current()->get('work-wechat-platform-plugin') as $item) {
            $plugin_list[$item['plugin']] = [
                'name' => $item['name'],
                'key' => $item['key'],
            ];
        }
        return $plugin_list;
    }

    public static function needExamine()
    {
        if (Setting::get('plugin.work-wechat-platform.create_business_examine')) {
            return true;
        }
        return false;
    }

    public static function bindBusinessId($business_id = null)
    {
        $bind_crop_id = app('plugins')->isEnabled('work-wechat-platform') ? Setting::get('plugin.work-wechat-platform')['bind_open_crop_id'] : 0;
        if ($business_id !== null) {
            return $bind_crop_id == $business_id && $business_id;
        }
        return $bind_crop_id;
    }

    /*
     * 将插件名从大驼峰转换成-分隔
     */
    public static function changePluginName($name)
    {
        //用正则将所有的大写字母替换成 `_字母`，如 `OrderDetail` 替换成 `_Order_Detail`
        $name = preg_replace('/[A-Z]/', '-\\0', $name); //_Order_Detail 说明：\\0为反向引用
        $name = strtolower($name);
        $name = ltrim($name, '-');
        return $name;
    }

    /*
     * 获取路由和页面菜单
     */
    public static function getMenu($business_id = 0)
    {
        return (new \business\admin\menu\BusinessMenu())->getMenu($business_id);
    }

    /*
     * 判断该企业被后台-企业平台管理插件授权的插件
     */
    public static function getEnablePlugins($business_id = 0)
    {
        if (!$business_id) $business_id = self::getBusinessId();
        $plat_setting = Business::uniacid()->find($business_id);
        return $plat_setting->auth_plugins ? unserialize($plat_setting->auth_plugins) : [];
    }

    /*
     * 判断是否开启了企业微信同步
     */
    public static function EnabledQyWx()
    {
        $setting = self::getQyWxSetting();
        return $setting['open_state'] ? true : false;
    }

    /*
     * 设置管理的企业ID
     */
    public static function setBusinessId($business_id)
    {
        Session::set('business_id', $business_id);
        $plat_log = PlatLog::getPlatLog();
        if ($plat_log->id) { //记录用户最后管理的企业
            $plat_log->final_plat_id = $business_id;
            $plat_log->save();
        }
    }

    /*
     * 获取当前管理的企业ID
     */
    public static function getBusinessId()
    {
        return Session::get('business_id') ?: self::$business_id ?: 0;
    }

    public static function staffTicketUrl($business_id = 0)
    {
        $business_id = $business_id ?: self::getBusinessId();
        $link_url = request()->getSchemeAndHttpHost() . yzApiUrl('plugin.work-wechat.frontend.ticket.index', ['i' => \YunShop::app()->uniacid]);
        $link_url = urlencode($link_url);
//        $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?';
        $setting = self::getQyWxSetting($business_id);
        if (!$setting['corpid'] || !$setting['agentid']) {
            return '';
        }
        $url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$setting['corpid']}&redirect_uri={$link_url}&response_type=code&scope=snsapi_privateinfo&state={$business_id}&agentid={$setting['agentid']}#wechat_redirect";
        return $url;
    }


    public static function getStaffTicketCodeUrl($business_id = 0)
    {
        $business_id = $business_id ?: self::getBusinessId();
        $res = [
            'ticket_url' => self::staffTicketUrl($business_id),
            'code_url' => '',
        ];
        if (!$res['ticket_url']) {
            return $res;
        }
        if (!$log = StaffTicketCode::uniacid()->business($business_id)->ticketUrl($res['ticket_url'])->first()) {
            $codeClass = new QrCodeHelper($res['ticket_url'], 'app/public/qr/business_ticket_code/' . $business_id);
            if (!$codeClass->url()) {
                return $res;
            }
            $log = StaffTicketCode::create([
                'uniacid' => \YunShop::app()->uniacid,
                'code_path' => $codeClass->filePath(),
                'code_url' => $codeClass->url(),
                'ticket_url' => $res['ticket_url'],
                'business_id' => $business_id,
            ]);
        }
        $res['code_url'] = $log->code_url;
        return $res;
    }


    /*
     * 获取企业微信设置
     */
    public static function getQyWxSetting($business_id = 0)
    {
        $business_id = $business_id ?: self::getBusinessId();
        $key = 'business.qy_wx_setting_' . $business_id;
        $setting = Setting::get($key) ?: [];
        $data = [
            'open_state' => $setting['open_state'] ? 1 : 0,
            'corpid' => $setting['corpid'] ?: '',
            'agentid' => $setting['agentid'] ?: '',
            'agent_secret' => $setting['agent_secret'] ?: '',
            'contact_secret' => $setting['contact_secret'] ?: '',
            'contact_token' => $setting['contact_token'] ?: '',
            'contact_aes_key' => $setting['contact_aes_key'] ?: '',
            'customer_secret' => $setting['customer_secret'] ?: '',
            'customer_token' => $setting['customer_token'] ?: '',
            'customer_aes_key' => $setting['customer_aes_key'] ?: '',
            'work_session_secret' => $setting['work_session_secret'] ?: '',
            'work_session_aes_key' => $setting['work_session_aes_key'] ?: '',
            'work_session_aes_key_version' => $setting['work_session_aes_key_version'] ?: '',
            'contact_notice_url' => request()->getSchemeAndHttpHost() . "/business/" . \YunShop::app()->uniacid . "/frontend/qyWxCallback?business_id=" . $business_id,
            'customer_notice_url' => \app\common\helpers\Url::absoluteApi('plugin.work-wechat.wevent.change-ext-contact.receive', ['type' => 5, 'crop_id' => $business_id]),
        ];
        return $data;
    }


    /*
     * 设置企业微信设置
     */
    public static function setQyWxSetting($request_data, $business_id = 0)
    {
        $business_id = $business_id ?: self::getBusinessId();
        $key = 'business.qy_wx_setting_' . $business_id;
        $data = [
            'open_state' => $request_data->open_state ? 1 : 0,
            'corpid' => trim($request_data->corpid) ?: '',
            'agentid' => trim($request_data->agentid) ?: '',
            'agent_secret' => trim($request_data->agent_secret) ?: '',
            'contact_secret' => trim($request_data->contact_secret) ?: '',
            'contact_token' => trim($request_data->contact_token) ?: '',
            'contact_aes_key' => trim($request_data->contact_aes_key) ?: '',
            'customer_secret' => trim($request_data->customer_secret) ?: '',
            'customer_token' => trim($request_data->customer_token) ?: '',
            'customer_aes_key' => trim($request_data->customer_aes_key) ?: '',
            'work_session_secret' => trim($request_data->work_session_secret) ?: '',
            'work_session_aes_key' => trim($request_data->work_session_aes_key) ?: '',
            'work_session_aes_key_version' => trim($request_data->work_session_aes_key_version) ?: '',
        ];
        return Setting::set($key, $data);
    }

    public static function getToken($business_id = 0, $config = array())
    {
        if (!$config) {
            $settings = static::getQyWxSetting($business_id);
            $config = [
                'corp_id' => $settings['corpid'],
                'agent_id' => $settings['agentid'],
                'secret' => $settings['agent_secret'],
            ];
        }
        $app = \app\common\facades\EasyWeChat::work($config);//获取配置信息
        $js = $app->jssdk;
        $url = \YunShop::request()->url;
        if ($url and ($end = strpos($url, '#')) !== false) {
            $url = substr($url, 0, $end);
        }
        $ticket = $js->getTicket();
        $getAgentTicket = $js->getAgentTicket();
        $timestamp = time();
        $noncestr = Support\Str::quickRandom(10);
        $str = sha1('jsapi_ticket=' . $ticket['ticket'] . '&noncestr=' . $noncestr . '&timestamp=' . $timestamp . '&url=' . $url);//企业的token
        $str2 = sha1('jsapi_ticket=' . $getAgentTicket['ticket'] . '&noncestr=' . $noncestr . '&timestamp=' . $timestamp . '&url=' . $url);//应用的token
        $data = [
            'corpid' => $config['corp_id'],
            'agentid' => $config['agent_id'],
            'jsapi_ticket' => $ticket['ticket'],
            'timestamp' => $timestamp,
            'noncestr' => $noncestr,
            'url' => $url,
            'token' => $str,
            'app_token' => $str2
        ];
        return $data;
    }
}
