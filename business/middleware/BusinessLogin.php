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


use app\common\traits\JsonTrait;
use business\common\services\BusinessService;
use business\common\services\SettingService;
use Closure;

class BusinessLogin
{
    use JsonTrait;

    public function handle($request, Closure $next)
    {
        $uid = \YunShop::app()->getMemberId();

        if (!$business_id = SettingService::getBusinessId()) {
            return $this->errorJson(BusinessService::BUSINESS_LIST_MSG, BusinessService::getBusinessListReturn());
        }

        if (!\business\common\models\Business::uniacid()->where('status', 1)->find($business_id)) {
            return $this->errorJson(BusinessService::BUSINESS_LIST_MSG, BusinessService::getBusinessListReturn());
        }

        $business_auth = BusinessService::checkBusinessRight($business_id, $uid);

        if ($business_auth['identity'] < 1) {
            return $this->errorJson(BusinessService::BUSINESS_LIST_MSG, BusinessService::getBusinessListReturn());
        }

        $all_right = $business_auth['identity'] > 1; //判断是否法人人、创始人、管理员
        if (!$this->checkRoute($business_auth['route'], $all_right)) {
            return $this->errorJson('Sorry,您没有操作权限');
        }

        return $next($request);
    }


    public function checkRoute($route_arr = [], $all_right = false)
    {
        $route = request()->path();
        $route = explode('/', $route);
        $true_route = '';

        $module = $route[2];

        if ($module == 'plugin') {
//            $module = $route[3];
            $module =  ucfirst(\Illuminate\Support\Str::camel($route[3]));
            foreach ($route as $k => $v) {
                if (in_array($k, [0, 1, 2])) continue;
                if ($v === null || $v === '' || $v === false) continue;
                $true_route .= '/' . $v;
            }
        } else {
            foreach ($route as $k => $v) {
                if (in_array($k, [0, 1, 2])) continue;
                if ($v === null || $v === '' || $v === false) continue;
                $true_route .= '/' . $v;
            }
        }

        if (substr($true_route, 0, 1) == '/') $true_route = substr($true_route, 1);;

        if (!$route_arr[$module]) {
            return false;
        }

        foreach ($route_arr[$module] as $v) {
            if ($v['route'] == $true_route && ($v['can'] || $all_right)) {
                return true;
            }
        }

        return false;

    }


}