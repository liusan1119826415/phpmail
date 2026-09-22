<?php
/**
 * Created by PhpStorm.
 * Date: 2023-07-13
 * Time: 15:00
 */

namespace app\frontend\modules\member\services;


use app\frontend\models\Member;
use app\frontend\modules\member\models\MemberModel;

class MemberEmailService extends MemberMobileService
{
    public function login()
    {
        $email   = \YunShop::request()->mobile;
        $password = \YunShop::request()->password;
        $uniacid  = \YunShop::app()->uniacid;
        $redirect_url = request()->yz_redirect;
        if (\Request::isMethod('post') && MemberService::validateEmail($email, $password)) {
            $has_email = MemberModel::checkEmail($uniacid, $email);
            if (!empty($has_email)) {
                $password = md5($password. $has_email->salt);
                $member_info = MemberModel::getUserInfoByEmail($uniacid, $email, $password)->first();
                if (is_null($member_info)) {
                    $error_count = $this->setLoginLimit($email);
                    if ($error_count > 0) {
                        return show_json(6, "密码错误！你还剩" . $error_count . "次机会");
                    } else {
                        return show_json(6, "密码错误次数已达5次，您的账号已锁定，请30分钟之后登录！");
                    }
                }
            } else {
                return show_json(7, "用户不存在");
            }
            $remain_time = $this->getLoginLimit($email);
            if ($remain_time) {
                return show_json(6, "账号锁定中，请".$remain_time."分钟后再登录");
            }
            if (!empty($member_info)) {
                MemberService::countReset($email);
                $member_info = $member_info->toArray();
                //生成分销关系链
                Member::createRealtion($member_info['uid']);
                $this->save(array_add($member_info,'password',$password), $uniacid);
                $data['uid'] = $member_info['uid'];
                $data['redirect_url'] = base64_decode($redirect_url);
                return show_json(1, $data);
            }
        } else {
            return show_json(6,"邮箱或密码错误");
        }
    }
}