<?php
/**
 * Created by PhpStorm.
 *
 * User: king/QQ：995265288
 * Date: 2018/6/12 下午4:30
 * Email: livsyitian@163.com
 */

namespace app\backend\modules\withdraw\controllers;


use app\backend\models\Withdraw;
use app\common\exceptions\ShopException;
use app\common\facades\Setting;
use app\common\services\Session;
use app\common\services\withdraw\PayedService;

class PayController extends PreController
{
    /**
     * 提现记录打款接口
     */
    public function index()
    {
        \Log::debug('提现记录打款接口++++++++++++++++++++');

        $check = $this->checkVerify();//打款验证
        if (!$check) {
            $this->errorJson(
                '提现验证失败或验证已过期', ['status' => -1]
            );
        }

        $result = (new PayedService($this->withdrawModel))->withdrawPay();

        return $result == true ? $this->successJson('打款成功') : $this->errorJson('打款失败，请刷新重试');
    }


    public function validatorWithdrawModel($withdrawModel)
    {
        if ($withdrawModel->status != Withdraw::STATUS_AUDIT) {
            throw new ShopException('状态错误，不符合打款规则！');
        }
    }

    private function checkVerify()
    {
        $set = Setting::getByGroup('pay_password')['withdraw_verify'] ?: [];
        if (empty($set) || empty($set['is_phone_verify'])) {
            return true;
        }
        $verify = Session::get('withdraw_verify');  //没获取到
        if ($verify && $verify >= time()) {
            return true;
        }
        return false;
    }



}
