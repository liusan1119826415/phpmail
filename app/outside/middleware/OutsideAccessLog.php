<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/4/29
 * Time: 11:43
 */

namespace app\outside\middleware;


use app\framework\Http\Request;
use app\outside\controllers\OutsideController;
use app\outside\services\OutsideLog;
use Illuminate\Routing\Route;

class OutsideAccessLog
{

    /**
     * @param $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {

        /**
         * @var Route $route
         */
        $route = $request->route();

        /**
         * @var OutsideController $controller
         */
        $controller = $route->getController();

        if ($controller->openAccessLog() || $controller->needAccessLog($route->getActionMethod())) {
            $this->writeAccessLog($request);
        }


        return $next($request);
    }

    public function writeAccessLog($request)
    {
        /**
         * @var Request $request
         */

        //过滤掉路由地址参数
        $requestData = $request->except([$request->getPathInfo()]);

        OutsideLog::info("[{$request->getClientIp()}]--[{$request->getPathInfo()}] ",$requestData);

    }
}