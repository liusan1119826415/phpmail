<?php

/**
 * Created by PhpStorm.
 * 
 * 
 *
 * Date: 2022/1/5
 * Time: 9:34
 */

namespace app\backend\modules\member\services;

use app\common\exceptions\ShopException;
use app\common\facades\Setting;
use GuzzleHttp\Client;
use Illuminate\Support\Str;
use app\common\models\member\InvitationCode;
use Carbon\Carbon;
use app\common\services\SmallQrCode;
class MessageService
{
    public function generateCode($expiresDays = 7, $max_uses, $is_limit, $maxAttempts = 100)
    {
        $attempts = 0;

        do {
            if ($attempts >= $maxAttempts) {
                throw new \Exception('无法生成唯一的邀请码，请重试');
            }

            $code = Str::random(8);
            $exists = InvitationCode::where('code', $code)->exists();
            $attempts++;
        } while ($exists);

        // 其余代码保持不变...
        if ($is_limit == 0) {
            $data = [
                'is_limit' => $is_limit,
                'code' => $code
            ];
        } else {
            $data = [
                'is_limit' => $is_limit,
                'code' => $code,
                'max_uses' => $max_uses,
                'expires_at' => Carbon::now()->addDays($expiresDays)->timestamp,
            ];
        }

        return InvitationCode::create($data);
    }

    public static function validateCode($code)
    {
        $invitation = InvitationCode::where('code', $code)
            ->where('used_count', '<', \DB::raw('max_uses'))
            ->where('expires_at', '>', time())
            ->where('status',1)
            ->first();

        if (!$invitation) {
            throw new ShopException('邀请码无效或已过期');
        }
        return $invitation;

    }

    public static function getUrl($route,$code)
    {
        $domain = request()->getSchemeAndHttpHost();

        if(empty($route) || self::isHttp($route)){
            return $route;
        }
        if(strpos($route, '/') !== 0){
            $route = '/' . $route;
        }
        if(!isset($params['i'])){
            $params['i'] = \YunShop::app()->uniacid;
        }
        if($code){
            $params['code'] = $code;
        }
        return  $domain.'/plugins/shop_server'.$route .  ($params ? '?'.http_build_query($params) : '');

    }

    public static function isHttp($url)
    {
        return (strpos($url,'http://') === 0 || strpos($url,'https://') === 0);
    }

    public function getMiniInviteCode($code_info)
    {
        $page = 'pages/index/index';
        $token = $this->getToken();

        if ($token === false) {
            \Log::debug('Failed to get access token');
            return false;
        }

        // 构造scene参数，只传递核心信息
        $scene = 'code='.$code_info->code;

        // 严格检查scene长度
        if (strlen($scene) > 32) {
            \Log::error('Scene parameter too long', ['scene' => $scene, 'length' => strlen($scene)]);
            return false;
        }

        // 确保page路径不包含查询参数
        if (strpos($page, '?') !== false) {
            $page = substr($page, 0, strpos($page, '?'));
        }

        $url = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=" . $token;
        $json_data = [
            "scene" => $scene,
            "page" => $page,
            "width" => 430, // 添加宽度参数
        ];

        $res = $this->curl_post($url, json_encode($json_data), []);

        // 增强错误处理
        if ($res === false) {
            \Log::error('CURL request to WeChat API failed');
            return false;
        }

        // 检查返回的是否是错误JSON
        $json_res = @json_decode($res, true);
        if ($json_res && isset($json_res['errcode']) && $json_res['errcode'] != 0) {
            \Log::error('WeChat API Error', [
                'errcode' => $json_res['errcode'],
                'errmsg' => $json_res['errmsg']
            ]);
            return false;
        }

        // 处理Logo并合成二维码
        $logoPath = storage_path('static/images/logo.png');
        if (!file_exists($logoPath)) {
            \Log::error('Logo file not found', ['path' => $logoPath]);
            // 如果没有logo，直接使用原始二维码
            $finalQrImage = $res;
        } else {
            $qr_img = file_get_contents($logoPath);
            $logo = SmallQrCode::drawCircle($qr_img);
            $finalQrImage = SmallQrCode::replaceMiddleLogo($res, $logo);
        }

        // 生成唯一文件名
        $filename = 'qrcode_' . md5($scene . time()) . '.png';
        $storagePath = storage_path('app/public/' . $filename);

        // 确保目录存在
        if (!is_dir(dirname($storagePath))) {
            mkdir(dirname($storagePath), 0755, true);
        }

        // 保存图片文件
        file_put_contents($storagePath, $finalQrImage);

        // 如果是Laravel，创建符号链接（如果还没创建的话）
        // 通常只需要运行一次：php artisan storage:link

        // 返回可访问的URL
        $res = uploadOss($storagePath,$filename);
        $code_info->mini_qrcode = $res['absolute_path'];
        $code_info->save();
        return $res['absolute_path'];
    }

    public function getToken()
    {
        $set = \Setting::get('plugin.min_app');
        $paramMap = [
            'grant_type' => 'client_credential',
            'appid' => $set['key'],
            'secret' => $set['secret'],
        ];
        //获取token的url参数拼接
        $strQuery="";
        foreach ($paramMap as $k=>$v){
            $strQuery .= strlen($strQuery) == 0 ? "" : "&";
            $strQuery .= $k."=".urlencode($v);
        }
        $getTokenUrl = "https://api.weixin.qq.com/cgi-bin/token?". $strQuery; //获取token的url
        $client = new Client;
        $res = $client->request('GET', $getTokenUrl);

        $data = json_decode($res->getBody()->getContents(), JSON_FORCE_OBJECT);

        if (isset($data['errcode'])) {
            \Log::debug('------生成小程序二维码获取token出错------', $data);
            return false;
        }
        return $data['access_token'];
    }


    private function curl_post($url = '', $postdata = '', $options = array())
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($options)) {
            curl_setopt_array($ch, $options);
        }
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }



}