<?php

namespace app\frontend\modules\member\services;

use app\common\exceptions\ShopException;
use app\common\facades\Setting;
use app\common\services\Session;
use app\frontend\modules\member\models\MemberTiktokModel;
use app\frontend\modules\member\models\MemberUniqueModel;
use Yunshop\DouyinSet\common\services\OpenApiService;

class MemberTiktokService extends MemberService
{
    const LOGIN_TYPE = 21;

    public function __construct()
    {
    }

    public function login()
    {
        try {
            $scope = \YunShop::request()->scope ?: 'userinfo';

            //商城默认头像
            $member_avatar = \Setting::get('shop.member')['headimg'];

            $set = $this->getSet();

            //ticket就是文档里面的code
            $code = \YunShop::request()->ticket;
            if (!empty($code)) {
                $openApiService = new OpenApiService($set);
                $accessToken = $openApiService->getAccessToken($code);
                $accessTokenData = $accessToken['data'];

                $open_userinfo = $openApiService->getUserinfo($accessTokenData['access_token'], $accessTokenData['open_id']);
                $userinfo = [
                    'access_token' => $accessTokenData['access_token'],
                    'expires_in' => 7200,
                    'refresh_token' => $accessTokenData['refresh_token'],
                    'openid' => $open_userinfo['data']['open_id'],
                    'nickname' => $open_userinfo['data']['nickname'],
                    'unionid' => $open_userinfo['data']['union_id'],
                    'headimgurl' => $open_userinfo['data']['avatar'] ?: $member_avatar,
                    'sex' => $open_userinfo['data']['gender'],
                    'province' => $open_userinfo['data']['province'],
                    'city' => $open_userinfo['data']['city'],
                    'country' => $open_userinfo['data']['country']
                ];

                \Log::debug('tiktok登录open_userinfo:',$open_userinfo);
                \Log::debug('tiktok登录userinfo:',$userinfo);

                //Login
                $member_id = $this->memberLogin($userinfo);
                \Log::debug('tiktok登录uid:' . $member_id);

                Session::set('member_id', $member_id);

                setcookie('Yz-appToken', encrypt($userinfo['access_token'] . '\t' . ($userinfo['expires_in'] + time()) . '\t' . $userinfo['openid'] . '\t' . $scope), time() + self::TOKEN_EXPIRE);

//                $random = $this->tiktok_session($userinfo);

//                $result = array('session' => $random, 'tiktok_token' => session_id(), 'uid' => $member_id);

                $this->_setClientRequestUrl();
                $redirect_url = $this->_getClientRequestUrl();
                redirect($redirect_url)->send();
                exit;
            }

        } catch (\Exception $e) {
            return show_json(0, $e->getMessage());
        }
        return show_json(0, 'code不存在');
    }

    /**
     * 设置客户端请求地址
     *
     * @return string
     */
    private function _setClientRequestUrl()
    {
        $pattern = '/(&t=([\d]+[^&]*))/';
        $t = time();

        if (\YunShop::request()->yz_redirect) {
            $yz_redirect = base64_decode(\YunShop::request()->yz_redirect);

            if (preg_match($pattern, $yz_redirect)) {
                $redirect_url = preg_replace($pattern, "&t={$t}", $yz_redirect);
            } else {
                $redirect_url = $yz_redirect . '&t=' . time();
            }

            Session::set('client_url', $redirect_url);
        } else {
            Session::set('client_url', '');
        }
    }

    /**
     * 获取客户端地址
     *
     * @return mixed
     */
    private function _getClientRequestUrl()
    {
        return Session::get('client_url');
    }

    /**
     * 小程序登录态
     * @param $user_info
     * @return string
     */
    function tiktok_session($user_info)
    {
        if (empty($user_info['openid'])) {
            return show_json(0, '用户信息有误');
        }

        $random = md5(uniqid(mt_rand()));

        $_SESSION['tiktok'] = array($random => iserializer(array('session_key' => $user_info['session_key'], 'openid' => $user_info['openid'])));

        return $random;
    }

    public function getFansModel($openid)
    {
        $model = MemberTiktokModel::getUId($openid);

        if (!is_null($model)) {
            $model->uid = $model->member_id;
        }

        return $model;
    }

    public function addMemberInfo($uniacid, $userinfo)
    {
        $uid = parent::addMemberInfo($uniacid, $userinfo);

        $this->addFansMember($uid, $uniacid, $userinfo);

        return $uid;
    }

    public function addFansMember($uid, $uniacid, $userinfo)
    {
        MemberTiktokModel::insertData(array(
            'uniacid' => $uniacid,
            'member_id' => $uid,
            'openid' => $userinfo['openid'],
            'nickname' => $userinfo['nickname'],
            'avatar' => $userinfo['headimgurl'],
            'created_at' => time(),
            'updated_at' => time(),
        ));
    }

    public function updateMemberInfo($member_id, $userinfo)
    {
        parent::updateMemberInfo($member_id, $userinfo);

        $record = array(
            'openid' => $userinfo['openid'],
            'nickname' => stripslashes($userinfo['nickname'])
        );

        MemberTiktokModel::updateData($member_id, $record);
    }

    /**
     * 会员关联表操作
     *
     * @param $uniacid
     * @param $member_id
     * @param $unionid
     */
    public function addMemberUnionid($uniacid, $member_id, $unionid)
    {
        MemberUniqueModel::insertData(array(
            'uniacid' => $uniacid,
            'unionid' => $unionid,
            'member_id' => $member_id,
            'type' => self::LOGIN_TYPE,
            'scope' => 2,
        ));
    }

    private function getSet()
    {
        if (!app('plugins')->isEnabled('douyin-set')) {
            throw new ShopException('抖音设置插件未开启');
        }
        $set = Setting::get('plugin.douyin_set');
        if (!$set['client_key'] || !$set['client_secret']) {
            throw new ShopException('抖音设置未配置，请前往配置');
        }
        return [
            'client_key' => $set['client_key'],
            'client_secret' => $set['client_secret'],
        ];
    }

    /**
     * 验证登录状态
     *
     * @return bool
     */
    public function checkLogged($login = null)
    {
        return MemberService::isLogged();
    }
}
