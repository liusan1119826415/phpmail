<?php

namespace app\frontend\modules\project\services\member;

use app\common\exceptions\AppException;
use app\common\facades\EasyWeChat;
use app\common\helpers\Cache;
use app\common\models\AccountWechats;
use app\common\models\Member;
use app\frontend\modules\member\models\MemberUniqueModel;
use app\frontend\modules\member\models\MemberWechatQrcodeModel;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redis;

class MemberWechatService
{
    protected $scene;

    // 缓存键前缀
    const CACHE_KEY_BIND_STATE = 'wechat_bind_state:';
    const CACHE_KEY_BIND_RESULT = 'wechat_bind_result:';

    // 缓存时间（分钟）
    const CACHE_TTL_BIND = 30;
    const CACHE_TTL_RESULT = 300;

    // 微信API URL
    const WECHAT_TOKEN_URL = 'https://api.weixin.qq.com/sns/oauth2/access_token';
    const WECHAT_USERINFO_URL = 'https://api.weixin.qq.com/sns/userinfo';
    const WECHAT_QR_CONNECT_URL = 'https://open.weixin.qq.com/connect/qrconnect';
    const WECHAT_SHOW_QR_URL = 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=';

    // 二维码场景值缓存时间（秒）
    const SCENE_CACHE_TTL = 120;

    // 错误提示
    const MSG_WECHAT_ALREADY_BOUND = '账号已经绑定微信';
    const MSG_ILLEGAL_REQUEST = '非法请求';
    const MSG_CODE_MISSING = 'Authorization code missing';
    const MSG_BIND_SUCCESS = '微信绑定成功';
    const MSG_WECHAT_BOUND_OTHER = '该微信已绑定其他账号';

    /**
     * 生成微信绑定二维码
     */
    public function generateQrCode(): array
    {
        $config = $this->getWechatConfig();
        $memberId = \YunShop::app()->getMemberId();

        $MemberUniqueModel = MemberUniqueModel::where('member_id', $memberId)->first();
        if ($MemberUniqueModel) {
            throw new AppException(self::MSG_WECHAT_ALREADY_BOUND);
        }
        $uniacid = \YunShop::app()->uniacid;
        $callback = ($_SERVER['REQUEST_SCHEME'] ? $_SERVER['REQUEST_SCHEME'] : 'http') . '://' . $_SERVER['HTTP_HOST'] . "/addons/yun_shop/api.php?i={$uniacid}&type=5&route=project.member.handleCallback";
        $state = Str::random(32);

        Cache::put(self::CACHE_KEY_BIND_STATE . $state, $memberId, now()->addMinutes(self::CACHE_TTL_BIND));

        $url = sprintf(
            self::WECHAT_QR_CONNECT_URL . "?appid=%s&redirect_uri=%s&response_type=code&scope=snsapi_login&state=%s#wechat_redirect",
            $config['appid'],
            urlencode($callback),
            $state
        );

        return [
            'qr_code_url' => $url,
            'state' => $state,
        ];
    }

    /**
     * 处理微信绑定回调
     */
    public function handleCallback()
    {
        try {
            \Log::debug("=====handleCallback====", request()->input());
            $config = $this->getWechatConfig();
            $state = request()->state;
            $member_id = Cache::get(self::CACHE_KEY_BIND_STATE . $state);
            if (!$member_id) {
                Cache::put(self::CACHE_KEY_BIND_RESULT . $state, [
                    'status' => 'fail',
                    'message' => self::MSG_ILLEGAL_REQUEST,
                ], self::CACHE_TTL_RESULT);
                throw new AppException(self::MSG_ILLEGAL_REQUEST);
            }

            $uniacid = \YunShop::app()->uniacid;
            $code = request()->code;
            if (!$code) {
                throw new AppException(self::MSG_CODE_MISSING);
            }
            $token = $this->_getTokenUrl($config['appid'], $config['app_secret'], $code);

            if (!empty($token) && is_array($token) && $token['errmsg'] == 'invalid code') {
                echo "invalid code";
                die;
            }

            $user_info = $this->_getUserInfoUrl($token['access_token'], $token['openid']);
            $MemberUniqueModel = MemberUniqueModel::where('unionid', $user_info['unionid'])->first();
            $member = Member::where('uid', $member_id)->first();
            if ($MemberUniqueModel) {
                echo self::MSG_WECHAT_BOUND_OTHER . $member->nickname;
                die;
            }
            MemberUniqueModel::replace([
                'uniacid' => $uniacid,
                'unionid' => $user_info['unionid'],
                'member_id' => $member_id,
                'type' => 5,
            ]);

            MemberWechatQrcodeModel::replace([
                'uniacid' => $uniacid,
                'member_id' => $member_id,
                'openid' => $user_info['openid'],
                'nickname' => $user_info['nickname'],
                'avatar' => $user_info['headimgurl'],
                'gender' => $user_info['sex'],
                'province' => '',
                'country' => '',
                'city' => '',
            ]);

            $member->nickname = $user_info['nickname'];
            $member->avatar = $user_info['headimgurl'];
            $member->gender = $user_info['sex'];
            $member->save();
            $result = [
                'success' => true,
                'message' => self::MSG_BIND_SUCCESS,
            ];
        } catch (\Exception $e) {
            \Log::error("微信绑定回调出错: " . $e->getMessage());
            $result = [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
        return $result;
    }

    /**
     * 获取微信配置
     */
    private function getWechatConfig(): array
    {
        if (!is_null(\app\common\modules\shop\ShopConfig::current()->get('wechat_qrcode_config'))) {
            $class = array_get(\app\common\modules\shop\ShopConfig::current()->get('wechat_qrcode_config'), 'class');
            $function = array_get(\app\common\modules\shop\ShopConfig::current()->get('wechat_qrcode_config'), 'function');
            return $class::$function();
        }
        return [];
    }

    /**
     * 获取access_token
     */
    private function _getTokenUrl($appId, $appSecret, $code)
    {
        $url = self::WECHAT_TOKEN_URL . "?appid=" . $appId . "&secret=" . $appSecret . "&code=" . $code . "&grant_type=authorization_code";
        return \Curl::to($url)->asJsonResponse(true)->get();
    }

    /**
     * 获取用户信息
     */
    private function _getUserInfoUrl($accesstoken, $openid)
    {
        $url = self::WECHAT_USERINFO_URL . "?access_token={$accesstoken}&openid={$openid}&lang=zh_CN";
        return \Curl::to($url)->asJsonResponse(true)->get();
    }

    /**
     * 获取二维码URL
     */
    private function getQrCodeUrl()
    {
        return self::WECHAT_SHOW_QR_URL . $this->getTicket();
    }

    /**
     * 获取ticket
     */
    private function getTicket()
    {
        return $this->createQR()['ticket'];
    }

    /**
     * 创建二维码
     */
    private function createQR()
    {
        $account = AccountWechats::getAccountByUniacid(\YunShop::app()->uniacid);
        $options = [
            'app_id' => $account->key,
            'secret' => $account->secret,
        ];
        $app = EasyWeChat::officialAccount($options);
        $qrcode = $app->qrcode;
        return $qrcode->temporary($this->getSceneValue(), self::SCENE_CACHE_TTL);
    }

    /**
     * 获取唯一场景值
     */
    private function getSceneValue()
    {
        $scene = sha1(rand(0, 999999));
        $result = Redis::get($scene);
        if (!$result) {
            Redis::setex($scene, self::SCENE_CACHE_TTL, 0);
            $this->scene = $scene;
            return $scene;
        } else {
            $this->getSceneValue();
        }
    }
}
