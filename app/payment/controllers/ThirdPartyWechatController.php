<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/3/28
 * Time: 上午6:50
 */

namespace app\payment\controllers;

use app\common\exceptions\ShopException;
use app\common\facades\EasyWeChat;
use app\common\facades\Setting;
use app\common\models\AccountWechats;
use app\common\services\Pay;
use app\common\services\PayFactory;
use app\payment\PaymentController;
use Yunshop\Freelogin\common\service\FreeLoginSign;


class ThirdPartyWechatController extends PaymentController
{
    private $appSecret;

    private $expires = 120;

    private $post;

    public function preAction()
    {
        parent::preAction();
        if (empty(\YunShop::app()->uniacid)) {
            $post = $this->getResponseResult();
            //免回调版
            if($post['attach']){
                if (\YunShop::request()->attach) {
                    \Setting::$uniqueAccountId = \YunShop::app()->uniacid = \YunShop::request()->attach;
                } else {
                    $this->attach = explode(':', $post['attach']);
                    \Setting::$uniqueAccountId = \YunShop::app()->uniacid = $this->attach[0];
                }
                \Log::debug('---------attach数组--------', \YunShop::app()->uniacid);
                AccountWechats::setConfig(AccountWechats::getAccountByUniacid(\YunShop::app()->uniacid));
            }else{
            //要回调版
                if (request()->i) {
                    Setting::$uniqueAccountId = \YunShop::app()->uniacid = request()->i;
                } else {
                    \Log::debug('---------i--error------', [request()->all()]);
                    die('i error');
                }
                $this->post = $_POST;
                $this->post['i'] = \YunShop::app()->uniacid;
                \Log::debug('---------i--------', [\YunShop::app()->uniacid,$this->post]);
            }
        }
    }

    private function verifySign()
    {
        if (!$this->post['appid']) {
            throw new \Exception('appid error');
        }
        if (!$this->post['timestamp']) {
            throw new \Exception('timestamp error');
        }
        if (!$this->post['sign']) {
            throw new \Exception('sign error');
        }
        if (!$this->post['out_trade_no']) {
            throw new \Exception('out_trade_no error');
        }

        $this->getAppData();
        $hfSign = new FreeLoginSign();
        $hfSign->setKey($this->appSecret);
        if (!$hfSign->payNotifyVerify($this->post)) {
            throw new \Exception('sign verify error');
        }
        return true;
    }

    public function notifyUrl()
    {
        try {
            $this->verifySign();
            $this->log(request()->all());

            if (request()->status == 'SUCCESS') {
                if (!$this->post['transaction_id']) {
                    throw new \Exception('transaction_id error');
                }
                if (!isset($this->post['total_fee'])) {
                    throw new \Exception('total_fee error');
                }
                $pay_type_id = PayFactory::THIRD_PARTY_MINI_PAY;

                $data = [
                    'total_fee'    => $this->post['total_fee'] ? : 0 ,
                    'out_trade_no' => $this->post['out_trade_no'],
                    'trade_no'     => $this->post['transaction_id'],
                    'unit'         => 'yuan',
                    'pay_type'     => '第三方微信小程序',
                    'pay_type_id'     => $pay_type_id,
                ];

                $this->payResutl($data);
            }
            die("SUCCESS");
        } catch (\Exception $e) {
            \Log::debug('-----第三方小程序支付-----'.$e->getMessage(),[request()->all()]);
            die($e->getMessage());
        }
    }

    //免回调版支付回调
    public function NoCallbackNotifyUrl()
    {
        $post = $this->getResponseResult();
        $this->log($post);
        $verify_result = $this->getSignResult($post);
        if ($verify_result) {
            $data = [
                'total_fee' => $post['total_fee'],
                'out_trade_no' => $post['out_trade_no'],
                'trade_no' => $post['transaction_id'],
                'unit' => 'fen',
                'pay_type' => '第三方微信小程序',
                'pay_type_id' => PayFactory::THIRD_PARTY_MINI_PAY,
            ];
            $this->payResutl($data);
            echo "success";
        } else {
            echo "fail";
        }
    }

    public function notifyH5Url()
    {
        $this->notifyUrl();
    }

    private function getAppData()
    {
        $appData = Setting::get('plugin.freelogin_set');

        if (is_null($appData) || 0 == $appData['status']) {
            throw new \Exception('应用未启用');
        }

        if (empty($appData['app_id']) || empty($appData['app_secret'])) {
            throw new \Exception('应用参数错误');
        }

        if ($appData['app_id'] != request()->input('appid')) {
            throw new \Exception('访问身份异常');
        }

        if (time() - request()->input('timestamp') > $this->expires) {
            throw new ShopException('访问超时');
        }
        $this->appSecret = $appData['app_secret'];
    }

    /**
     * 支付日志
     *
     * @param $post
     */
    public function log($post)
    {
        //访问记录
        Pay::payAccessLog();
        //保存响应数据
        Pay::payResponseDataLog($post['out_trade_no'], '第三方微信小程序支付', json_encode($post));
    }

    /**
     * 获取回调结果
     *
     * @return array|mixed|\stdClass
     */
    public function getResponseResult()
    {
        $input = file_get_contents('php://input');
        if (!empty($input) && empty($_POST['out_trade_no'])) {
            //禁止引用外部xml实体
            $disableEntities = libxml_disable_entity_loader(true);

            $data = json_decode(json_encode(simplexml_load_string($input, 'SimpleXMLElement', LIBXML_NOCDATA)), true);

            libxml_disable_entity_loader($disableEntities);

            if (empty($data)) {
                exit('fail');
            }
            if ($data['result_code'] != 'SUCCESS' || $data['return_code'] != 'SUCCESS') {
                exit('fail');
            }
            $post = $data;
        } else {
            $post = $_POST;
        }

        return $post;
    }

    /**
     * 签名验证
     *
     * @return bool
     */
    public function getSignResult($post)
    {
        $min_set = \Setting::get('plugin.freelogin_set');
        $pay = [
            'weixin_appid' => $min_set['key'],
            'weixin_secret' => $min_set['secret'],
            'weixin_mchid' => $min_set['mchid'],
            'weixin_apisecret' => $min_set['api_secret'],
            'weixin_cert' => '',
            'weixin_key' => ''
        ];
        $payment = $this->getEasyWeChatApp($pay);
        try {
            $message = (new \EasyWeChat\Payment\Notify\Paid($payment))->getMessage();
            return $message;
        } catch (\Exception $exception) {

            \Log::debug('微信签名验证：' . $exception->getMessage());
            return false;
        }

    }

    /**
     * 创建支付对象
     *
     * @param $pay
     * @return \EasyWeChat\Payment\Payment
     */
    public function getEasyWeChatApp($pay)
    {
        $options = [
            'app_id' => $pay['weixin_appid'],
            'secret' => $pay['weixin_secret'],
            'mch_id' => $pay['weixin_mchid'],
            'key' => $pay['weixin_apisecret'],
            'cert_path' => $pay['weixin_cert'],
            'key_path' => $pay['weixin_key']
        ];
        $app = EasyWeChat::payment($options);
        return $app;
    }
}
