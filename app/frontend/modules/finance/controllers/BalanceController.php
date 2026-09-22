<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/4/2
 * Time: 下午5:37
 */

namespace app\frontend\modules\finance\controllers;

use app\common\exceptions\AppException;
use app\common\exceptions\ShopException;
use app\common\facades\Setting;
use app\common\helpers\Cache;
use app\common\payment\PaymentManager;
use app\common\services\credit\ConstService;
use app\common\services\finance\BalanceChange;
use app\common\events\payment\RechargeComplatedEvent;
use app\common\services\finance\TransferDeductPointService;
use app\common\services\password\PasswordService;
use app\common\services\PayFactory;
use app\common\components\ApiController;
use app\frontend\modules\finance\models\Balance;
use app\frontend\modules\finance\models\Balance as BalanceCommon;
use app\frontend\modules\finance\models\BalanceTransfer;
use app\frontend\modules\finance\models\BalanceConvertLove;
use app\frontend\modules\finance\models\BalanceRecharge;
use app\frontend\modules\finance\payment\types\RechargePaymentTypes;
use app\frontend\modules\finance\services\BalanceRechargeSetService;
use app\frontend\modules\finance\services\BalanceRecordService;
use app\frontend\modules\finance\services\BalanceService;
use app\backend\modules\member\models\Member;
use app\frontend\modules\member\models\MemberModel;
use Illuminate\Support\Facades\DB;
use Yunshop\ProjectParty\services\HttpService;
use Yunshop\ShopEsignV2\common\models\Scene;
use Yunshop\ShopEsignV2\common\models\ShopContract;
use Yunshop\ShopEsignV2\common\services\CommonService;
use Yunshop\YunSign\common\models\Contract;
use Yunshop\YunSign\common\models\ContractLog;
use Yunshop\YunSign\common\service\QcloudCosService;

class BalanceController extends ApiController
{
    private $memberInfo;

    private $model;

    public $transactionActions = ['transfer'];

    private $money;

    protected $publicAction = ['alipay'];

    protected $ignoreAction = ['alipay'];

    public $memberModel;

    public $recipient;

    /**
     * @var BalanceService
     */
    public $balanceSet;

    public $uniacid;


    public function preAction()
    {
        parent::preAction();
        $this->balanceSet = new BalanceService();
        $this->uniacid = \YunShop::app()->uniacid;
    }

    /**
     * 余额首页数据
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $data = (new BalanceService())->getIndexData();
        return $this->successJson('成功', $data);
    }

    /**
     * 会员余额页面信息，（余额设置+会员余额值）
     * @return \Illuminate\Http\JsonResponse
     */
    public function balance()
    {
        if ($memberInfo = $this->getMemberInfo()) {
            $result = (new BalanceService())->getBalanceSet();
            $result['credit2'] = $memberInfo->credit2;
            $result['buttons'] = app('Payment')->setPaymentTypes(new RechargePaymentTypes())->getPaymentButton();
            $result['typename'] = '充值';
//            $result['love_name'] = (app('plugins')->isEnabled('designer') == 1) ? LOVE_NAME : '爱心值';
            $result['love_name'] = LOVE_NAME;
            $result['convert'] = (new BalanceService())->convertSet();
            $result['remark'] = $this->getRechargeRemark();
            $result['need_sign'] = 0;
            $balance_set = Setting::get('finance.balance');
            $diy_amount_set = $balance_set['diy_amount'];
            $diy_amount_arr = [];
            if (!empty($diy_amount_set)) {
                $min_recharge = $balance_set['min_recharge'];
                foreach ($diy_amount_set as $item) {
                    if ($min_recharge&&$min_recharge>$item['amount']) {
                        continue;
                    }
                    array_push($diy_amount_arr, $item['amount']);
                }
            }
            $result['min_recharge'] = $balance_set['min_recharge'];
            $result['diy_amount'] = $diy_amount_arr;
            if (app('plugins')->isEnabled('shop-esign-v2')&&app('plugins')->isEnabled('yun-sign')) {
                if (!CommonService::white($memberInfo->uid)) {
                    $shop_contract_set = Setting::get('plugin.shop_esign_v2');
                    $scene_id = $shop_contract_set['recharge_scene_id'];
                    if ($scene_id) {
                        $scene = Scene::find($scene_id);
                        if ($scene) {
                            $result['sign_info']['contract_name'] = $scene->name;
                            $contract = ShopContract::uniacid()
                                ->where('member_id', $memberInfo->uid)
                                ->where('scene_id', $scene_id)
                                ->where('status', 1)
                                ->first();
                            if (!$contract) {
                                $result['need_sign'] = 1;
                            } else {
                                $contract = Contract::find($contract->contract_id);
                                $contract_log = ContractLog::uniacid()->where(['contract_id'=>$contract->id,'status'=>99])->first();
                                if ($contract_log) {
                                    $cloudCosService = new QcloudCosService();
                                    $signed_time = $contract_log->created_at;
                                    $signed_time = date('Y-m-d', strtotime($signed_time));
                                    $result['sign_info']['signed_time'] = $signed_time;
                                    $result['sign_info']['signed_url'] = $cloudCosService->getUrlV5($contract->contract_doc_url);
                                    $result['sign_info']['contract_id'] = $contract->id;
                                }
                            }
                        }
                    }
                }
            }
            return $this->successJson('获取数据成功', $result);
        }
        return $this->errorJson('未获取到会员数据');
    }

    public function memberBalance()
    {
        if ($memberInfo = $this->getMemberInfo()) {
            /**
             * @var Member $memberInfo
             */
            $data['credit2'] = $memberInfo->credit2;
            $data['has_password'] = $memberInfo->yzMember->hasPayPassword();
            $data['need_password'] = $this->needTransferPassword();
            $data['deduct_point'] = [
                'status' => (bool)Setting::get('finance.balance.deduct_status'),
                'deduct_balance' => Setting::get('finance.balance.deduct_balance', 1),
                'deduct_point' => Setting::get('finance.balance.deduct_point', 1),
            ];

            return $this->successJson('获取数据成功', $data);
        }
        return $this->errorJson('未获取到会员数据');
    }

    /**
     * 会员余额转化爱心值
     * @return \Illuminate\Http\JsonResponse
     */
    public function conver()
    {
        if (!$this->balanceSet->convertSet()) {
            return $this->errorJson('未开启余额转化');
        }
        $memberInfo = $this->getMemberInfo();
        if ($memberInfo) {
            $result = (new BalanceService())->getBalanceSet();
            $result['credit2'] = $memberInfo->credit2;
            $result['rate'] = $this->balanceSet->convertRate();
            return $this->successJson('获取数据成功', $result);
        }
        return $this->errorJson('未获取到会员数据');

    }


    //余额充值+充值优惠
    public function recharge()
    {
        if (empty(\YunShop::request()->recharge_money) || \YunShop::request()->recharge_money == 'NaN') {
            return $this->errorJson('充值金额不能为空,请填写充值金额');
        }

        $balance_set = Setting::get('finance.balance');
        if ($balance_set['min_recharge'] && (int)$balance_set['min_recharge'] > \YunShop::request()->recharge_money) {
            return $this->errorJson('充值金额少于最低起充金额');
        }

        //$result = (new BalanceService())->rechargeSet() ? $this->rechargeStart() : '未开启余额充值';

        $result = (new BalanceService())->rechargeSet();
        if (!$result) {
            return $this->errorJson('未开启余额充值');

        }
        //之后优化余额充值使用新接口：finance.balance-recharge.confirm
         $this->model = \app\frontend\modules\finance\services\BalanceRechargeService::temporary();

        if ($result === true) {
            $type = intval(\YunShop::request()->pay_type);

//            $verify = (new BalanceRechargeSetService())->verifyRecharge($type, \YunShop::request()->recharge_money);
//            if ($verify !== true) {
//                return $this->errorJson($verify);
//            }

            //测试使用
//            if (true) {
//                event(new RechargeComplatedEvent(['order_sn'=>$this->model->ordersn, 'pay_sn'=> $this->model->ordersn, 'total_fee'=>$this->model->actual_pay, 'unit'=> 'yuan',]));
//                return $this->successJson('支付接口对接成功', ['ordersn' => $this->model->ordersn]);
//            }

            $array = [
                PayFactory::PAY_WEACHAT,
                PayFactory::PAY_YUN_WEACHAT,
                PayFactory::PAY_Huanxun_Quick,
                PayFactory::PAY_Huanxun_Wx,
                PayFactory::WFT_PAY,
                PayFactory::WFT_ALIPAY,
                PayFactory::PAY_WECHAT_HJ,
                PayFactory::PAY_ALIPAY_HJ,
                PayFactory::PAY_WECHAT_JUEQI,
                PayFactory::WECHAT_NATIVE,
                PayFactory::WECHAT_H5,
                PayFactory::XFPAY_ALIPAY,
                PayFactory::XFPAY_WECHAT,
                PayFactory::WECHAT_MIN_PAY,
                PayFactory::LESHUA_ALIPAY,
                PayFactory::LESHUA_WECHAT,
                PayFactory::LSP_PAY,
                PayFactory::CONVERGE_ALIPAY_H5_PAY,
                PayFactory::EPLUS_WECHAT_PAY,
                PayFactory::EPLUS_MINI_PAY,
                PayFactory::EPLUS_ALI_PAY,
                PayFactory::THIRD_PARTY_MINI_PAY
            ];
            if (in_array($this->model->type, [PayFactory::EPLUS_ALI_PAY, PayFactory::EPLUS_WECHAT_PAY, PayFactory::EPLUS_MINI_PAY])) {
                $user = \Yunshop\EplusPay\services\SettingService::getUser(\YunShop::app()->getMemberId());
                if (!$user || !$user->is_bind_mobile) {
                    return $this->errorJson('请先绑定智E+账户手机号', ['eplus_bind_mobile' => 1]);
                }
            }

            if (in_array($type, $array)) {
                return $this->successJson('支付接口对接成功', array_merge(['ordersn' => $this->model->ordersn], $this->payOrder()));
            }
            //头条支付
            if (in_array($type, [PayFactory::PAY_WECHAT_TOUTIAO, PayFactory::PAY_ALIPAY_TOUTIAO])) {
                $data['ordersn'] = $this->model->ordersn;
                $data['orderInfo'] = $this->payOrder();
                return $this->successJson('支付接口对接成功', $data);
            }

            //贵州银行支付
            if(app('plugins')->isEnabled('bgzchina-pay')&&\Setting::get('plugin.bgzchina-pay.is_open')&&$type==app(PaymentManager::class)->get('bgzchinaPay')->getId()){
                $payResult= $this->payOrder();
                if($payResult['code']==1){
                    return $this->successJson('支付接口对接成功', isset($payResult['data']['url'])?['url'=>$payResult['data']['url']]:$payResult['data']['config']);
                }
                return $this->errorJson($payResult['msg']);
            }

            //微信小程序plus支付
            if(app('plugins')->isEnabled('miniprogram-plus-pay')&&\Setting::get('plugin.miniprogram-plus-pay.is_open')&&$type==app(PaymentManager::class)->get('miniProgramPlusPay')->getId()){
                $payResult= $this->payOrder();
                if($payResult['code']==1){
                    return $this->successJson('支付接口对接成功', $payResult['data']);
                }
                return $this->errorJson($payResult['msg']);
            }

            //app支付宝支付添加新支付配置
            if ($type == PayFactory::PAY_APP_ALIPAY) {
                $isnewalipay = \Setting::get('shop_app.pay.newalipay');
                return $this->successJson('支付接口对接成功', ['ordersn' => $this->model->ordersn, 'isnewalipay' => $isnewalipay]);
            } else {
                return $this->successJson('支付接口对接成功', ['ordersn' => $this->model->ordersn]);
            }
        }
        //app支付宝新旧版值
        //处理报错返回信息格式不对
        $res = json_decode(json_encode($result), true);
        $res_text = '';
        foreach ($res as $item) {
            $res_text .= $item[0];
        }
        $result = $res_text ?: $result;
        return $this->errorJson($result);
    }

    //余额充值，如果是支付宝支付需要二次请求 alipay 支付接口
    public function alipay()
    {
        $orderSn = \YunShop::request()->order_sn;

        $this->model = BalanceRecharge::ofOrderSn($orderSn)->withoutGlobalScope('member_id')->first();
        if ($this->model) {
            return $this->successJson('支付接口对接成功', $this->payOrder());
        }

        return $this->errorJson('充值订单不存在');
    }

    public function cloudWechatPay()
    {
        $orderSn = \YunShop::request()->ordersn;

        $this->model = BalanceRecharge::ofOrderSn($orderSn)->withoutGlobalScope('member_id')->first();
        if ($this->model) {
            return $this->successJson('支付接口对接成功', $this->payOrder());
        }

        return $this->errorJson('充值订单不存在');
    }

    public function wechatPayJueqi()
    {
        $orderSn = \YunShop::request()->order_pay_id;

        $this->model = BalanceRecharge::ofOrderSn($orderSn)->withoutGlobalScope('member_id')->first();
        if ($this->model) {
            return $this->successJson('支付接口对接成功', $this->payOrder());
        }

        return $this->errorJson('充值订单不存在');
    }

    //余额转让
    public function transfer()
    {
        $result = (new BalanceService())->transferSet() ? $this->transferStart() : '未开启'.(Setting::get('shop.lang.zh_cn.member_center.credit') ?: '余额').'转让';

        return $result === true ? $this->successJson('转让成功') : $this->errorJson($result);
    }

    //余额转化爱心值
    public function convertLoveValue()
    {
        $result = (new BalanceService())->convertSet() ? $this->convertStart() : '未开启'.(Setting::get('shop.lang.zh_cn.member_center.credit') ?: '余额').'转化';
        return $result === true ? $this->successJson('转化成功') : $this->errorJson($result);
    }

    /**
     * @return BalanceRecordService
     */
    protected function getBalanceRecordService(): BalanceRecordService
    {
        return new BalanceRecordService();
    }

    /**
     * 余额明细页面数据
     * @return \Illuminate\Http\JsonResponse
     */
    public function record()
    {
        $search = request()->search;
        $date = date('Y-m', strtotime($search['date']));
        $record_data = $this->getBalanceRecordService()->getRecordData();

        // 选择的日期 && 日期参数存在 && 小于等于第一页。这想条件符合证明第一页没有数据就直接返回
        if (!$record_data['record_list']['data'][$date] && $search['date'] && request()->page <= 1) {
            return $this->errorJson("暂无数据！");
        } elseif (false === $record_data) {
            return $this->errorJson("暂无数据！");
        }
        return $this->successJson("成功", $record_data);
    }

    /**
     * 获取余额的业务类型
     * 根据当前会员的余额明细表所拥有的服务类型
     * @return \Illuminate\Http\JsonResponse
     */
    public function getServiceTypeList()
    {
        $member_id = \YunShop::app()->getMemberId();
        $redis_key = "ServiceTypeList:" . $member_id;
        if (Cache::has($redis_key)) {
            $service_type_arr = Cache::get($redis_key);
        } else {
            $service_type_arr = (new ConstService(''))->sourceComment();
            $service_type_key = Balance::getServiceType();
            $service_type_arr = $service_type_key->map(function ($serviceKey) use ($service_type_arr) {
                return [
                    'id' => $serviceKey,
                    'name' => $service_type_arr[$serviceKey],
                ];
            })->values();
            //根据当前会员的余额明细查出所拥有的服务类型，存进缓存
            Cache::put($redis_key, $service_type_arr, 1440);
        }
        return $this->successJson('成功', $service_type_arr);
    }

    //余额转换爱心值
    public function convertStart()
    {
        if (!class_exists('\Yunshop\Love\Common\Services\LoveChangeService')) {
            return $this->errorJson('未开启爱心值插件');
        }
        if (!$this->getMemberInfo()) {
            return '未获取到会员信息';
        }
        if (\YunShop::request()->convert_amount <= 0) {
            return '转化金额必须大于零';
        }
        if ($this->memberInfo->credit2 < \Yunshop::request()->convert_amount) {
            return '转化'.(Setting::get('shop.lang.zh_cn.member_center.credit') ?: '余额').'不能大于您的'.(Setting::get('shop.lang.zh_cn.member_center.credit') ?: '余额');
        }
        $this->model = new BalanceConvertLove();
        $this->model->fill($this->getConvertData());
        $validator = $this->model->validator();
        if ($validator->fails()) {
            return $validator->messages();
        }

        if ($this->model->save()) {
            //$result = (new BalanceService())->balanceChange($this->getChangeBalanceDataToTransfer());
            $result = (new BalanceChange())->convert($this->getChangeConverData());
            if ($result === true) {
                if ($this->awardMemberLove() !== true) {
                    (new BalanceChange())->convertCancel($this->getConvertCancel());  //爱心值交易失败，回滚余额
                    $this->errorJson('转化失败');
                }
                $this->model->status = BalanceConvertLove::CONVERT_STATUS_SUCCES;
                if ($this->model->save()) {
                    return true;
                }
            }
            return '修改转化状态失败';
        }
        return '转化写入出错，请联系管理员';
    }

    private function getMemberModel()
    {
        $memberModel = Member::where('uid', \YunShop::app()->getMemberId())->first();
        if ($memberModel) {
            return $memberModel;
        }
        throw new AppException('未获取到会员信息');
    }

    private function needTransferPassword()
    {
        return (new PasswordService())->isNeed('balance', 'transfer');
    }

    //获取会员信息
    private function getMemberInfo()
    {
        return $this->memberInfo = Member::where('uid', \YunShop::app()->getMemberId())->first();
    }

    //充值开始
    private function rechargeStart()
    {
        if (!$this->getMemberInfo()) {
            return '未获取到会员数据,请重试！';
        }
        $this->model = new BalanceRecharge();
        $this->model->fill($this->getRechargeData());
        $validator = $this->model->validator();
        if ($validator->fails()) {
            return $validator->messages();
        }
        if ($this->model->save()) {
            return true;
        }
        return '充值写入失败，请联系管理员';
    }

    private function recipient()
    {
        $recipient = request()->input('recipient');
        $project_party_set = Setting::get('plugin.project_party');
        if (app('plugins')->isEnabled('project-party')&&$project_party_set['is_balance_transfer']) {
            $res = (new HttpService())->selectMember($recipient);
            if (!$res['status']) {
                throw new ShopException('请求服务商系统失败'.$res['msg']);
            }
            $mobile = $res['data']['member_tel'];
            $member_info = MemberModel::select(['uid','nickname','avatar','realname'])->uniacid()->where('mobile', $mobile)->first();
            if (!$member_info) {
                throw new ShopException('会员不存在');
            }
            $this->recipient = $member_info->uid;
        } else {
            $this->recipient = $recipient;
        }
        return $this->recipient;
    }

    private function amount()
    {
        return request()->input('transfer_money');
    }

    protected function memberId()
    {
        return \YunShop::app()->getMemberId();
    }

    protected function password()
    {
        return request()->input('password');
    }

    //余额转让开始
    private function transferStart()
    {
        if ($this->needTransferPassword()) (new PasswordService())->checkPayPassword($this->memberId(), $this->password());
        if (!$this->getMemberInfo()) {
            return '未获取到会员信息';
        }
        if ($this->amount() <= 0) {
            return '转让金额必须大于零';
        }
        if ($this->memberInfo->credit2 < $this->amount()) {
            return '转让余额不能大于您的余额';
        }
        try {
            $recipient = $this->recipient();
        } catch (ShopException $exception) {
            return $exception->getMessage();
        }
        if ($this->memberInfo->uid == $recipient) {
            return '转让者不能是自己';
        }
        if (!Member::getMemberInfoById($recipient)) {
            return '被转让者不存在';
        }
        if ((new BalanceService())->teamTransferSet()) {
            if (!(new BalanceService())->teamTransfer($recipient)) {
                return '转让者不是团队成员';
            }
        }
        $need_deduct_point = (new TransferDeductPointService())->getNeedDeductPoint($this->amount());
        if (\Setting::get('finance.balance.deduct_status') && $need_deduct_point > $this->memberInfo->credit1) {
            $point_name = TransferDeductPointService::getPointName();
            return "转账需扣除{$need_deduct_point}{$point_name}，您的{$point_name}不足";
        }
        $this->model = new BalanceTransfer();
        $this->model->fill($this->getTransferData());
        $validator = $this->model->validator();
        if ($validator->fails()) {
            return $validator->messages();
        }
        if ($this->model->save()) {
            //$result = (new BalanceService())->balanceChange($this->getChangeBalanceDataToTransfer());
            $result = (new BalanceChange())->transfer($this->getChangeBalanceDataToTransfer());
            if ($result === true) {
                $this->model->status = BalanceTransfer::TRANSFER_STATUS_SUCCES;
                if ($this->model->save()) {
                    return true;
                }
            }
            return '修改转让状态失败';
        }
        return '转让写入出错，请联系管理员';
    }

    private function getConvertData()
    {
        return array(
            'uniacid' => \Yunshop::app()->uniacid,
            'member_id' => \Yunshop::app()->getMemberId(),
            'covert_amount' => \Yunshop::request()->convert_amount,
            'status' => BalanceConvertLove::CONVERT_STATUS_ERROR,
            'order_sn' => $this->getTransferOrderSN(),
            'remark' => '余额转化爱心值',
        );
    }

    private function getChangeConverData()
    {
        return array(
            'member_id' => $this->model->member_id,
            'remark' => '会员【ID:' . $this->model->member_id . '】余额转化爱心值会员【ID：' . $this->model->member_id . '】' . $this->model->covert_amount . '元',
            'source' => ConstService::SOURCE_CONVERT,
            'relation' => $this->model->order_sn,
            'operator' => ConstService::OPERATOR_MEMBER,
            'operator_id' => $this->model->member_id,
            'change_value' => $this->model->covert_amount,
        );
    }

    private function getConvertCancel()
    {
        return array(
            'member_id' => $this->model->member_id,
            'remark' => '会员【ID:' . $this->model->member_id . '】余额转化失败【ID：' . $this->model->member_id . '】' . $this->model->covert_amount . '元',
            'source' => ConstService::SOURCE_CONVERT_CANCEL,
            'relation' => $this->getTransferOrderSN(),
            'operator' => ConstService::OPERATOR_MEMBER,
            'operator_id' => $this->model->member_id,
            'change_value' => $this->model->covert_amount,
        );
    }

    /**
     * 转化爱心值
     * @return bool
     */
    private function awardMemberLove()
    {
        //统一走爱心值交易类型接口
        $_LoveChangeService = new  \Yunshop\Love\Common\Services\LoveChangeService('usable');
        $data = [
            'member_id' => $this->model->member_id,
            'change_value' => $this->calculateLoveValue(),
            'operator' => ConstService::OPERATOR_MEMBER,
            'operator_id' => $this->model->member_id,
            'remark' => '会员【ID:' . $this->model->member_id . '】余额转化爱心值会员【ID：' . $this->model->member_id . '】' . $this->model->covert_amount . '元',
            'relation' => $this->model->order_sn,
        ];

        $result = $_LoveChangeService->conver($data);
        if ($result !== true) {
            DB::rollBack();
            return false;
        }
        DB::commit();
        return true;
    }

    /**
     * 计算爱心值
     * @return string
     */
    private function calculateLoveValue()
    {
        return bcdiv(bcmul($this->model->covert_amount, $this->balanceSet->convertRate(), 2), 100, 2);
    }

    //余额转让详细记录数据
    private function getChangeBalanceDataToTransfer()
    {
        return array(
            'member_id' => $this->model->transferor,
            'remark' => '会员【ID:' . $this->model->transferor . '】余额转让会员【ID：' . $this->model->recipient . '】' . $this->model->money . '元',
            'source' => ConstService::SOURCE_TRANSFER,
            'relation' => $this->model->order_sn,
            'operator' => ConstService::OPERATOR_MEMBER,
            'operator_id' => $this->model->transferor,
            'change_value' => $this->model->money,
            'recipient' => $this->model->recipient,
        );
    }

    private function getTransferData()
    {
        return array(
            'uniacid' => \YunShop::app()->uniacid,
            'transferor' => \YunShop::app()->getMemberId(),
            'recipient' => $this->recipient,
            'money' => trim(\YunShop::request()->transfer_money),
            'status' => BalanceTransfer::TRANSFER_STATUS_ERROR,
            'order_sn' => $this->getTransferOrderSN(),
        );
    }

    /**
     * 生成唯一转让订单号
     * @return string
     */
    private function getTransferOrderSN()
    {
        $orderSn = createNo('TS', true);
        while (1) {
            if (!BalanceTransfer::ofOrderSn($orderSn)->first()) {
                break;
            }
            $orderSn = createNo('TS', true);
        }
        return $orderSn;
    }

    //充值记录表data数据
    private function getRechargeData()
    {
        //$change_money = substr(\YunShop::request()->recharge_money, 0, strpos(\YunShop::request()->recharge_money, '.')+3);
        $change_money = \YunShop::request()->recharge_money;
        if (\YunShop::request()->pay_type == PayFactory::PAY_APP_ALIPAY) {
            //支付宝APP支付充值金额超过6位数，支付宝会自动对超过6位的小数点后的数值进行四舍五入
            $length = strlen(intval($change_money));
            if ($length >= 5 && $change_money > intval($change_money)) {
                throw new ShopException('APP支付宝充值超5位数的金额不能拥有小数，请重新填写');
            }
        }
        return array(
            'uniacid' => \YunShop::app()->uniacid,
            'member_id' => $this->memberInfo->uid,
            'old_money' => $this->memberInfo->credit2 ?: 0,
            'money' => floatval($change_money),
            'new_money' => $change_money + $this->memberInfo->credit2,
            'ordersn' => BalanceRecharge::createOrderSn('RV', 'ordersn'),
            'type' => intval(\YunShop::request()->pay_type),
            'status' => BalanceRecharge::PAY_STATUS_ERROR,
            'remark' => '会员前端充值',
        );
    }

    /**
     * 会员余额充值支付接口
     */
    private function payOrder($pay_type = null)
    {
        $pay_type = is_null($pay_type) ? $this->model->type : $pay_type;

        $pay = PayFactory::create($pay_type);


        $result = $pay->doPay($this->payData(), $pay_type);
        \Log::info('++++++++++++++++++', $result);
        if ($pay_type == 1) {
            $result['js'] = json_decode($result['js'], 1);
        }

        if (in_array($pay_type, [PayFactory::PAY_WECHAT_HJ, PayFactory::PAY_ALIPAY_HJ])) {
            if ($result['msg'] !== '成功') {
                throw new AppException($result['msg']);
            }
        }

        if (in_array($pay_type, [PayFactory::CONVERGE_ALIPAY_H5_PAY])) {
            if ($result['code'] != 200) {
                throw new AppException($result['msg']);
            }
        }

        if (in_array($pay_type, [PayFactory::EPLUS_MINI_PAY, PayFactory::EPLUS_WECHAT_PAY])) {
            $result = json_decode($result['payInfo'], true) ?: [];
            if ($result['timeStamp']) {
                $result['timestamp'] = $result['timeStamp'];
            }
        }

        if ($pay_type == PayFactory::EPLUS_ALI_PAY) {
            $result = ['pay_url' => $result['payInfo'] ?: ''];
        }


        \Log::debug('余额充值 result', $result);
        return $result;
    }

    /**
     * 支付请求数据
     *
     * @return array
     * @Author yitian
     */
    private function payData()
    {
        $array = array(
            'subject' => '会员充值',
            'body' => '会员充值金额' . $this->model->money . '元:' . \YunShop::app()->uniacid,
            'amount' => $this->model->money,
            'member_id' => \YunShop::app()->getMemberId(),
            'order_no' => $this->model->ordersn,
            'extra' => ['type' => 2],
            'ask_for' => 'recharge',
        );
        if ($this->model->type == PayFactory::PAY_CLOUD_ALIPAY) {
            $array['extra'] = ['type' => 2, 'pay' => 'cloud_alipay'];
        }

        if ($this->model->type == PayFactory::PAY_Huanxun_Quick) {
            $array['extra'] = ['type' => 2, 'pay' => 'quick'];
        }
        return $array;
    }

    /**
     * 获取充值活动说明
     * @return array
     */
    private function getRechargeRemark()
    {
        $balance_set = Setting::get('finance.balance');
        $shop_set = Setting::get('shop');

        if (!$this->balanceSet->rechargeSet() || !$this->balanceSet->rechargeActivityStatus()) {
            if ($balance_set['charge_reward_swich'] == 1) {
                //活动中的赠送积分不受控
                return [
                    'reward_points' => ['rate' => $balance_set['charge_reward_rate'] ?: 100, 'name' => $shop_set['credit1'] ? $shop_set['credit1'] : '积分'],
                ];
            }
            return [];//未开启活动或余额充值
        }

        $data = [
            'recharge_activity_start' => date('Y-m-d H:i:s', $this->balanceSet->rechargeActivityStartTime()),
            'recharge_activity_end' => date('Y-m-d H:i:s', $this->balanceSet->rechargeActivityEndTime()),
            'recharge_activity_fetter' => $this->balanceSet->rechargeActivityFetter(),//最多参与次数
            'proportion_status' => $this->balanceSet->proportionStatus(),//0-固定金额，1-百分比
            'sale' => $this->balanceSet->rechargeSale(),
        ];
        if ($balance_set['charge_reward_swich'] == 1) {
            $data['reward_points'] = ['rate' => $balance_set['charge_reward_rate'] ?: 100, 'name' => $shop_set['credit1'] ? $shop_set['credit1'] : '积分'];
        }
        return $data;
    }

}
