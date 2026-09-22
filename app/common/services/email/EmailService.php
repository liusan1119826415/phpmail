<?php
/**
 * Created by PhpStorm.
 * Date: 9/13/23
 * Time: 4:54 PM
 */

namespace app\common\services\email;


use app\common\facades\Setting;
use app\common\helpers\Cache;
use app\common\services\Session;
use Illuminate\Support\Facades\Mail;

class EmailService
{
    private $mail_set;

    public function __construct()
    {
        $this->init();
    }

    private function init()
    {
        $this->mail_set = Setting::get('shop.email');
    }

    public function send($mail)
    {
        $msg = $this->mail_set['msg']?:'您的邮箱验证码是:';
        $subject = $this->mail_set['subject']?:'主题:';
        if (empty($this->mail_set)||!$this->mail_set['send_email']||!$this->mail_set['password']) {
            return $this->show_json(0, '邮箱信息未配置');
        }
        \Config::set('mail.host', ($this->mail_set['email_type'] == 1) ? 'smtp.163.com' : 'smtp.qq.com');
        \Config::set('mail.port', 465);
        \Config::set('mail.username', $this->mail_set['send_email']);
        \Config::set('mail.password', $this->mail_set['password']);
        Mail::raw($msg.$this->getCode($mail), function ($mailService) use ($mail, $subject) {
            $mailService->from($this->mail_set['send_email'], '邮件显示的信息');
            $mailService->subject($subject);
            $mailService->to($mail);
        });
        if (!empty(Mail::failures())) {
            return $this->show_json(0, '发送失败');
        }
        return $this->show_json(1, '发送成功');
    }

    public function checkCode($mail, $code, $key = '')
    {
        if ((Session::get('mail_code_time'.$key) + 60 * 5) < time()) {
            return $this->show_json(0, '验证码已过期,请重新获取');
        }
        if (Session::get('code_mail'.$key) != $mail) {
            return $this->show_json(0, '邮箱错误,请重新获取');
        }
        //增加次数验证
        if (Cache::get('mail_code_num_'.$mail) >= 5) {
            return $this->show_json(0, '验证码错误次数过多,请重新获取');
        }
        if (Session::get('mail_code'.$key) != $code) {
            Cache::increment('mail_code_num_'.$mail);
            return $this->show_json(0, '验证码错误,请重新获取');
        }
        return $this->show_json(1);
    }

    private function getCode($mail, $key = '')
    {
        $code = rand(1000, 9999);
        Session::set('mail_code_time'.$key, time());
        Session::set('mail_code'.$key, $code);
        Session::set('code_mail'.$key, $mail);
        Cache::forget('mail_code_num_'.$mail);
        return $code;
    }

    private function show_json($status = 1, $return = null)
    {
        return array(
            'status' => $status,
            'json' => $return,
        );
    }
}