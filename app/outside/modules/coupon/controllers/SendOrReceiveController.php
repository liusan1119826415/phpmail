<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/4/16
 * Time: 11:40
 */

namespace app\outside\modules\coupon\controllers;


use app\backend\modules\coupon\services\Message;
use app\backend\modules\coupon\services\MessageNotice;
use app\common\exceptions\ApiException;
use app\common\helpers\ArrayHelper;
use app\common\models\Coupon;
use app\common\models\CouponLog;
use app\common\models\Member;
use app\common\models\MemberCoupon;
use app\common\modules\coupon\models\PreMemberCoupon;
use app\framework\Database\Eloquent\Collection;
use app\outside\controllers\OutsideController;

class SendOrReceiveController extends OutsideController
{

    public $accessMethod = ['send','receive'];

    protected $userFieldKey;

    protected function paramValidate()
    {
        $this->validate([
            'member' => 'required',
            'user_type' => 'required',
            'coupon' => 'required',
        ]);


    }

    /**
     * @param $mode
     * @param $queryValue
     * @return \app\framework\Database\Eloquent\Collection|null
     * @throws ApiException
     */
    protected function selectMember($mode, $queryValue)
    {
        switch ($mode) {
            case 1: //会员ID
                $field_key = 'uid';
                break;
            case 2: //会员手机号
                $field_key = 'mobile';
                break;
            default:
                throw new ApiException('不存在的用户类型');
                break;
        }

        $members = Member::uniacid()->select('uid','mobile')->whereIn($field_key, $queryValue)->get()->makeHidden(['username','avatar_image']);

        if ($members->isEmpty()) {
            throw new ApiException('用户不存在');
        }

        if ($members->count() != count($queryValue)) {
            $diff = array_diff($queryValue, $members->pluck($field_key)->all());
            throw new ApiException("发放优惠券失败，请确认：" . implode(",", $diff) . "会员是否存在");
        }

        $this->userFieldKey = $field_key;

        return $members;
    }


    //手动发放优惠券
    public function send()
    {

        $this->paramValidate();

        $member_info = ArrayHelper::unreliableDataToArray(request()->input('member'));

        //获取发放的数量
        $sendTotal = request()->input('send_num',1);

        if ($sendTotal < 1) {
            return $this->errorJson('发放数量必须为整数且不能小于 1');
        }

        $couponModel = Coupon::getCouponById(request()->input('coupon'));

        $members = $this->selectMember(request()->input('user_type'), $member_info);

        $memberCount = $members->count();


        if ($memberCount > 100) {
            return $this->errorJson('一次性最多给100个用户发放优惠券');
        }

        $this->couponValidate($couponModel);


        if ($couponModel->total != -1) {

            //优惠券已发送数量
            $getTotal = MemberCoupon::uniacid()->where("coupon_id", $couponModel->id)->count();
            //优惠券剩余可发送数量
            $lastTotal = $couponModel->total - $getTotal;

            // 优惠券有限,并且发放数量超过限制
            if ($lastTotal < 0) {
                return $this->errorJson("剩余优惠券不足(准备发放" . $sendTotal * $memberCount . "张,此前已超发" . abs($lastTotal) . "张)",['error_code'=> 'COUPON004']);
            }

            if ($sendTotal * $memberCount > $lastTotal) {
                return $this->errorJson("剩余优惠券不足(准备发放" . $sendTotal * $memberCount . "张,剩余{$lastTotal}张)",['error_code'=> 'COUPON004']);
            }

        }
        //发放优惠券
        $messageData = [
            'title' => htmlspecialchars_decode($couponModel->resp_title),
            'image' => tomedia($couponModel->resp_thumb),
            'description' => $couponModel->resp_desc ? htmlspecialchars_decode($couponModel->resp_desc) : '亲爱的 [nickname], 你获得了 1 张 "' . $couponModel->name . '" 优惠券',
            'url' => $couponModel->resp_url ?: yzAppFullUrl('home'),
        ];
        $res = $this->sendCoupon($members,$couponModel,$sendTotal, $messageData);
        if ($res['success_num']) {
            //发送获取通知
            foreach ($res['success'] as $uid => $num) {
                if ($num) {
                    MessageNotice::couponNotice($couponModel->id, $uid);
                }
            }

            $fail_user = collect($res['fail'])->filter()->mapWithKeys(function ($num,$uid) use ($members) {
                $member = $members->firstWhere('uid', $uid);
                if ($member) {
                    return [$member->{$this->userFieldKey} => $num];
                }
                return null;
            })->filter()->all();

            return $this->successJson('发送优惠券完成', [
                'success_num' => $res['success_num'],
                'fail_num' => $res['fail_num'],
                'fail_user' => $fail_user,
            ]);
        }

        return $this->errorJson('优惠券发送失败',['error_code'=> 'COUPON500']);

    }

    //会员领取优惠券
    public function receive()
    {
        $this->paramValidate();

        $member_info = intval(request()->input('member'));

        //获取发放的数量
        $sendTotal = request()->input('send_num',1);

        if ($sendTotal < 1) {
            return $this->errorJson('领取数量必须为整数且不能小于 1');
        }

        $couponModel = Coupon::getCouponById(request()->input('coupon'));

        $members = $this->selectMember(request()->input('user_type'), [$member_info]);

        $this->couponValidate($couponModel);

        if ($couponModel->total != -1) {

            //优惠券已发送数量
            $getTotal = MemberCoupon::uniacid()->where("coupon_id", $couponModel->id)->count();
            //优惠券剩余可发送数量
            $lastTotal = $couponModel->total - $getTotal;

            // 优惠券有限,并且发放数量超过限制
            if ($lastTotal < 0) {
                return $this->errorJson("剩余优惠券不足(准备发放". $sendTotal ."张,此前已超发" . abs($lastTotal) . "张)",['error_code'=> 'COUPON004']);
            }

            if ($sendTotal > $lastTotal) {
                return $this->errorJson("剩余优惠券不足(准备发放". $sendTotal ."张,剩余{$lastTotal}张)",['error_code'=> 'COUPON004']);
            }
        }

        $member = $members->first();
        $memberCoupon = (new PreMemberCoupon());
        $memberCoupon->init($member, $couponModel, $sendTotal);
        try {
            $memberCoupon->generate();
        } catch (\Exception $e) {
            return $this->errorJson('领取失败',['error_code'=> 'COUPON003','error_msg'=> $e->getMessage()]);
        }

        return $this->successJson('领取成功');
    }

    /**
     * @param $couponModel
     * @throws ApiException
     */
    protected function couponValidate($couponModel)
    {
        if (!$couponModel || !$couponModel->status) {
            throw new ApiException('优惠券已下架,请先重新上架',['error_code'=> 'COUPON001']);
        }

        //判断优惠券是否过期
        $timeLimit = $couponModel->time_limit;
        if ($timeLimit == 1 && (time() > $couponModel->time_end->timestamp)) {
            throw new ApiException('优惠券已过期',['error_code'=> 'COUPON002']);

        }
    }


    /**
     * 发放优惠券
     * @param Collection $members
     * @param coupon $couponModel
     * @param int $sendTotal
     * @param array $messageData
     * @return array
     */
    public function sendCoupon($members,$couponModel, $sendTotal,$messageData)
    {

        $data = [
            'uniacid' => \YunShop::app()->uniacid,
            'coupon_id' => $couponModel->id,
            'get_type' => 0,
            'used' => 0,
            'get_time' => strtotime('now'),
        ];

        $successObj = [];
        $failObj = [];

        foreach ($members as $member) {
            $current_success = 0;
            $current_fail = 0;
            for ($i = 0; $i < $sendTotal; $i++) {
                $memberCoupon = new MemberCoupon();
                $data['uid'] = $member->uid;
                $res = $memberCoupon->create($data);

                //写入log
                if ($res) { //发放优惠券成功
                    $current_success += 1;
                    $log = '接口发放成功：1张优惠券( ID为 ' . $couponModel->id . ' )给用户( Member ID 为 ' .  $member->uid . ' )';
                } else { //发放优惠券失败
                    $current_fail += 1;
                    $log = '发放失败: 优惠券( ID为 ' . $couponModel->id . ' )给用户( Member ID 为 ' .  $member->uid . ' )时失败!';
                    \Log::info($log);
                }
                $this->log($log, $couponModel,$member->uid);
            }
            if ($current_success) {
                $this->sendMessageNotice($messageData,$couponModel,$member);
            }

            $successObj[$member->uid] = $current_success;
            $failObj[$member->uid] = $current_fail;
        }

        return [
            'success_num' => array_sum($successObj),
            'fail_num' => array_sum($failObj),
            'success' => $successObj,
            'fail' => $failObj,
        ];
    }

    //写入日志
    protected function log($log, $couponModel, $memberId)
    {
        $logData = [
            'uniacid' => \YunShop::app()->uniacid,
            'logno' => $log,
            'member_id' => $memberId,
            'couponid' => $couponModel->id,
            'paystatus' => 0,
            'creditstatus' => 0,
            'paytype' => 0,
            'getfrom' => 0,
            'status' => 0,
            'createtime' => time(),
        ];
        $res = CouponLog::create($logData);
        return $res;
    }


    protected function sendMessageNotice($messageData,$couponModel, $member)
    {
        if (!empty($messageData['title'])) { //没有关注公众号的用户是没有 openid
            $templateId = \Setting::get('coupon_template_id'); //模板消息ID
            $dynamicData = [
                'nickname' => $member->nickname,
                'couponname' => $couponModel->name,
            ];
            $messageData['title'] = $this->dynamicMsg($messageData['title'], $dynamicData);
            $messageData['description'] = $this->dynamicMsg($messageData['description'], $dynamicData);

            Message::message($messageData, $templateId, $member->uid); //默认使用微信"客服消息"通知, 对于超过 48 小时未和平台互动的用户, 使用"模板消息"通知
        }
    }

    //动态显示内容
    protected function dynamicMsg($msg, $data)
    {
        if (preg_match('/\[nickname\]/', $msg)) {
            $msg = str_replace('[nickname]', $data['nickname'], $msg);
        }
        if (preg_match('/\[couponname\]/', $msg)) {
            $msg = str_replace('[couponname]', $data['couponname'], $msg);
        }
        return $msg;
    }
}