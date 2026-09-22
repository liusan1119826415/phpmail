<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/3/29
 * Time: 下午2:38
 */

namespace app\common\services;

use app\common\helpers\WeSession;
use app\common\helpers\YunSession;
use app\platform\modules\system\models\SystemSetting;

/**
 * Session控制类
 */
class Session
{

    const PREFIX = 'yunzshop_';

    /**
     * 设置session
     * @param String $name session name
     * @param Mixed $data session data
     * @param Int $time 超时时间(秒)
     */
    public static function set($name, $data, $time = 864000)
    {
        $expire = time() + $time;
        $session_data = array();
        $session_data['data'] = $data;
        $session_data['expire'] = $expire;
        $_SESSION[self::PREFIX . $name] = $session_data;
        //todo 临时处理
        if ($name == 'member_id') {
            Session::set('m_uniacid',\YunShop::app()->uniacid);
        }
    }

    /**
     * 读取session
     * @param String $name session name
     * @return Mixed
     */
    public static function get($name)
    {
        if (strpos($name, '.')) {
            $array = explode('.', $name);
            $name = array_shift($array);

            $key = implode('.', $array);

        }
        if (isset($_SESSION[self::PREFIX . $name])) {
            if (isset($key)) {
                $value = array_get($_SESSION[self::PREFIX . $name]['data'], $key);
            } else {
                $value = $_SESSION[self::PREFIX . $name]['data'];
            }

            //todo 临时处理
            if ($name == 'member_id' && Session::get('m_uniacid') != \YunShop::app()->uniacid) {

                return false;
            }
            return $value;
        }
        return false;
    }

    /**
     * 清除session
     * @param String $name session name
     */
    public static function clear($name)
    {
        unset($_SESSION[self::PREFIX . $name]);
    }

    public static function put($name, $data, $time = 864000)
    {
        self::set($name, $data, $time);
    }

    public static function remove($name)
    {
        self::clear($name);
    }

    public static function has($name)
    {
        if (strpos($name, '.')) {
            $array = explode('.', $name);
            $name = array_shift($array);
            $key = implode('.', $array);
        }

        if (!isset($_SESSION[self::PREFIX . $name])) {
            return false;
        }
        if (isset($key) && !array_has($_SESSION[self::PREFIX . $name]['data'], $key)) {
            return false;
        }

        return true;
    }

    public static function flash($key, $value)
    {
        self::put($key, $value);
    }

    public static function factory($uniacid)
    {
        if (config('app.framework') == 'platform') {
            $loginset = SystemSetting::settingLoad('loginset', 'system_loginset');
            $expire = 7 * 86400;
            if ($loginset['session_time']) {
                $expire = intval($loginset['session_time']) * 86400;
            }
            YunSession::start($uniacid, Utils::getClientIp(),$expire);
        } else {
            WeSession::start($uniacid, CLIENT_IP);
        }
    }
}
