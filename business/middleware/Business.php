<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2021/9/22
 * Time: 13:37
 */

namespace business\middleware;


use app\common\services\Session;
use app\common\traits\JsonTrait;
use app\common\facades\Setting;
use app\frontend\modules\member\services\factory\MemberFactory;
use business\common\models\PlatLog;
use business\common\services\BusinessService;
use business\common\services\SettingService;
use Closure;

class Business
{
    use JsonTrait;

    public function handle($request, Closure $next)
    {
        \YunShop::app()->uniacid = request()->uniacid;
        Setting::$uniqueAccountId = request()->uniacid;

        $memberService = MemberFactory::create($type);
        $is_login = $memberService->checkLogged();


//        $route = \request()->path();
//        $route = explode('/', $route);
//        \YunShop::app()->uniacid = $route[1];
//        Setting::$uniqueAccountId = $route[1];

        if (!app('plugins')->isEnabled('business-pc') || !\Yunshop\BusinessPc\services\SettingService::getSetting()['open_state']) {
            return $this->errorJson('企业管理PC端未开启');
        }

        if (!$uid = \YunShop::app()->getMemberId()) { //判断当前用户是否已经前端登录
            return $this->errorJson(BusinessService::LOGIN_MSG, BusinessService::getLoginReturn());
        }

        if (!$business_id = SettingService::getBusinessId()) {  //如果当前未选择管理的企业
            if ($business_id = PlatLog::getPlatLogId()) {   //获取企业历史管理记录
                SettingService::setBusinessId($business_id); //设置管理的企业
            }
        }

        return $next($request);
    }


}