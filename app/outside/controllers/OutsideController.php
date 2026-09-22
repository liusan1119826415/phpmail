<?php
/**
 * Created by PhpStorm.
 * 
 *
 *
 * Date: 2022/1/5
 * Time: 15:35
 */

namespace app\outside\controllers;

use app\outside\middleware\OutsideAccessLog;
use Illuminate\Http\Request;
use app\common\exceptions\ApiException;
use app\common\services\utils\EncryptUtil;
use app\outside\modes\OutsideAppSetting;
use app\outside\services\NotifyService;
use app\outside\services\OutsideAppService;
use Illuminate\Support\Facades\DB;
use app\common\models\AccountWechats;
use app\common\components\BaseController;


class OutsideController extends BaseController
{

    /**
     * @var OutsideAppSetting
     */
    public $outsideApp;


    protected $noAppMode = false; //无应用模式


    public $needVerifySign = true;

    public $accessLog = false; //所以方法都记录访问日志

    public $accessMethod = []; //指定方法记录访问日志


    public function __construct()
    {
        parent::__construct();
        $this->middleware([OutsideAccessLog::class]);
    }

    /**
     * 前置action
     * @throws ApiException
     */
    public function preAction()
    {
        parent::preAction();

        $this->apiVerify();

    }

    /**
     * @throws ApiException
     */
    protected function apiVerify()
    {

        if ($this->noAppMode) {
            return;
        }

        $appID = trim(request()->input('app_id'));

        if (!is_numeric($appID)) {
            throw new ApiException('不符合标准的appID');
        }

        $appSet = OutsideAppSetting::where('app_id', $appID)->first();

        if (!$appSet) {
            throw new ApiException('应用不存在');
        }

        if (!$appSet->is_open) {
            throw new ApiException('应用已关闭');
        }

        $this->outsideApp = $appSet;

        //$this->setCurrentUniacid($appSet->uniacid);

        //开启签名验证，签名验证失败
        if(!$appSet->sign_required && !$this->appSignVerify()) {
            throw new ApiException('签名验证失败');
        }
    }

    final public function openAccessLog()
    {
        return $this->accessLog;
    }

    final public function needAccessLog($action)
    {
        return in_array($action, $this->accessMethod) || in_array('*',
                $this->accessMethod) || $this->accessMethod == '*';
    }

    final protected function appSignVerify()
    {
        //过滤掉路由地址参数
        $requestData = request()->except([request()->getRequestUri(),request()->getPathInfo(),'i',]);
        return (new NotifyService($requestData))->verifySign();

    }

    //设置公众号
    protected function setCurrentUniacid($uniacid)
    {
        \Setting::$uniqueAccountId = \YunShop::app()->uniacid = $uniacid;
        AccountWechats::setConfig(AccountWechats::getAccountByUniacid(\YunShop::app()->uniacid));
    }

    /**
     * url参数验证
     *
     * @param array $rules
     * @param Request|null $request
     * @param array $messages
     * @param array $customAttributes
     *
     * @throws ApiException
     */
    public function validate(array $rules, Request $request = null, array $messages = [], array $customAttributes = [])
    {
        if (!isset($request)) {
            $request = request();
        }
        $validator = $this->getValidationFactory()->make($request->all(), $rules, $messages, $customAttributes);

        if ($validator->fails()) {
            throw new ApiException($validator->errors()->first());
        }
    }


     //todo 这里应该定义下业务错误编号，好让第三方判断处理
    /**
     * 接口返回错误JSON 格式
     * @param string $message 提示信息
     * @param array $data 返回数据
     * @return \Illuminate\Http\JsonResponse
     */
    public function errorJson($message = '失败', $data = [])
    {
        $data = array_merge(['error_code'=> 'ERR000','error_msg'=> $message], $data);

        response()->json([
            'result' => 0,
            'msg' => $message,
            'data' => $data
        ], 200, ['charset' => 'utf-8'])->send();
        exit();
    }
}