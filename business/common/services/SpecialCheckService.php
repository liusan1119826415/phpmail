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


use app\common\helpers\Cache;
use business\admin\menu\BusinessMenu;
use business\common\models\Department;
use business\common\models\Premission;
use business\common\models\Staff;
use Illuminate\Support\Facades\DB;
use Exception;

class SpecialCheckService
{


    public static function onlyBind($crop_id = 0)
    {
        $bind_crop_id = \Setting::get('plugin.work-wechat-platform.bind_open_crop_id');
        if ($crop_id && $bind_crop_id == $crop_id) {
            return true;
        }
        return false;
    }

    /*
     * 判断当前会员是否该企业的客服
     */
    public static function isCustom($business_id = 0, $uid = 0, $forget = 0)
    {
        $business_id = $business_id ?: SettingService::getBusinessId();
        $uid = $uid ?: \YunShop::app()->getMemberId();

        if ($forget) {
            BusinessService::flush(0, $uid);
        }

        $key = 'BusinessRight_IsCustom_MemberId' . $uid . '_BusinessId' . $business_id;
        if ($return_data = Cache::get($key, null, [BusinessService::getModuleRedisKey(\YunShop::app()->uniacid), BusinessService::getBusinessRedisKey($business_id), BusinessService::getMemberRedisKey($uid)])) {
            return $return_data > 0 ? true : false;
        }

        $return_data = -1;

        if (app('plugins')->isEnabled('yun-chat')) {
            $employee_info = \Yunshop\YunChat\common\models\Employee::getOneByUid($uid);
            if ($employee_info && $employee_info->crop_id == $business_id) {
                $return_data = 1;
            }
        }

        Cache::put($key, $return_data, 1, [BusinessService::getModuleRedisKey(\YunShop::app()->uniacid), BusinessService::getBusinessRedisKey($business_id), BusinessService::getMemberRedisKey($uid)]);

        return $return_data > 0 ? true : false;
    }

}