<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 17/3/2
 * Time: 上午7:37
 */

namespace app\frontend\modules\member\controllers;

use app\common\components\BaseController;

use app\common\helpers\Client;
use app\common\services\Session;
use app\common\services\Utils;
use app\frontend\modules\member\models\SubMemberModel;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;

class LogoutController extends BaseController
{
    public function index()
    {
        if (Client::is_nativeApp()) {
            $token = \YunShop::request()->yz_token;
            $member = SubMemberModel::getMemberByNativeToken($token);
            $member->access_token_2 = '';
            $member->save();
        } else {
            $member_id = \YunShop::app()->getMemberId();

            // 动态判断当前的域名
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $isLocal = (strpos($host, '192.168.') === 0 || $host === 'localhost');

            if ($isLocal) {
                // 本地退出 cookie 清除
                setcookie('Yz-Token', '', time() - 3600, '/');
                setcookie('Yz-appToken', '', time() - 3600, '/');
                setcookie(session_name(), '', time() - 3600, '/');
            } else {
                // 正式环境退出 cookie 清除
                $topLevelDomain = config("app.COOKIE_URL");
                setcookie('Yz-Token', '', time() - 3600, '/', $topLevelDomain);
                setcookie('Yz-appToken', '', time() - 3600, '/', $topLevelDomain);
                setcookie(session_name(), '', time() - 3600, '/', $topLevelDomain);
                setcookie(session_name(), '', time() - 3600, '/addons/yun_shop', $topLevelDomain);
            }

            session_destroy();
            $this->destroy($member_id);
        }

        return $this->successJson('退出成功');
    }




    private function destroy($member_id)
    {
        $row = array();
        $row[':member_id'] = $member_id;
        $row[':openid'] = Utils::getClientIp();

        $sql = 'DELETE FROM ' . DB::getTablePrefix() . 'core_sessions WHERE `member_id` = :member_id and `openid` = :openid';
        return DB::delete($sql, $row) == 1;
    }
}
