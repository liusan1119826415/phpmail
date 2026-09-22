<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2022/1/7
 * Time: 18:28
 */

namespace app\outside\modules\member\controllers;


use app\common\exceptions\ApiException;
use app\common\helpers\Url;
use app\common\models\Member;
use app\outside\controllers\OutsideController;
use app\outside\modules\member\services\CreateMemberService;
use Illuminate\Support\Facades\DB;

class InfoController extends OutsideController
{
    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function query()
    {
        $mobile = request()->input('phone');


        if (empty($mobile)) {
            return $this->errorJson('搜索条件为空');
        }

//        $member = Member::select('uid', 'nickname', 'realname', 'mobile', 'avatar')
//            ->where('realname', 'like', '%' . $search['member'] . '%')
//            ->orWhere('mobile', 'like', '%' . $search['member'] . '%')
//            ->orWhere('nickname', 'like', '%' . $search['member'] . '%');

        $member_list = Member::uniacid()->select(['uid', 'avatar', 'nickname', 'realname', 'mobile', 'createtime',
            'credit1', 'credit2',])
            ->leftJoin('yz_member_del_log', 'mc_members.uid', '=', 'yz_member_del_log.member_id')
            ->join('yz_member', 'mc_members.uid', '=', 'yz_member.member_id')
            ->where('mc_members.mobile', 'like', '%' . $mobile . '%')
            ->get();

        if ($member_list->isNotEmpty()) {
            $member_list->map(function ($member) {
                $member->createtime =  date('Y-m-d H:i:s', $member->createtime);
                return $member;
            });

        }

        return $this->successJson('list', $member_list);

    }
    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function detail()
    {
        $mobile = request()->input('mobile');
        $uid = request()->input('uid');


        if (empty($mobile)) {
            return $this->errorJson('手机号为空');
        }

        $pattern = "/^1[3456789]\d{9}$/"; // 正则表达式
        if (!preg_match($pattern, $mobile)) {
            return $this->errorJson('手机号码不正确');
        }

        $member = Member::uniacid()->select(['uid', 'avatar', 'nickname', 'realname', 'mobile', 'createtime',
            'credit1', 'credit2',])
            ->leftJoin('yz_member_del_log', 'mc_members.uid', '=', 'yz_member_del_log.member_id')
            ->join('yz_member', 'mc_members.uid', '=', 'yz_member.member_id')
            ->where('mc_members.mobile', $mobile);
        if (isset($uid)) {
            $member = $member->where('uid', $uid);
        }

        $member = $member->first();

        if (empty($member)) {
            return  $this->errorJson('没有该会员');
        }

        $member->createtime =  date('Y-m-d H:i:s', $member->createtime);

        return $this->successJson('成功', $member);

    }

    public function update()
    {

        $this->validate([
            'user_id' => 'required',
            'user_type' => 'required',
        ]);

        $user_code = request()->input('user_id');
        $mode = request()->input('user_type');


        $nickname = request()->input('nickname');

        $realname = request()->input('realname');


        if (empty($nickname) && empty($realname)) {
            throw new ApiException('未填写需更新的数据', ['error_code' => 'RE400', 'error_msg' => '未填写需更新的数据']);
        }

        switch ($mode) {
            case 1: //会员ID
                $field_key = 'uid';
                break;
            case 2: //会员手机号
                $field_key = 'mobile';
                break;
            default:
                throw new ApiException('参数错误', ['error_code' => 'RE400', 'error_msg' => 'user_type 参数错误']);
                break;
        }

        $memberModel = Member::uniacid()
            ->select(['uid', 'avatar', 'nickname', 'realname', 'mobile', 'createtime',
            'credit1', 'credit2',])->withoutDeleted();

        $memberModel->where("mc_members.{$field_key}", $user_code);

        $member = $memberModel->first();

        if (!$member) {
            return $this->errorJson('会员不存在', ['error_code' => 'USER404', 'error_msg' => '会员不存在']);
        }

        if ($nickname) {
            $member->nickname = $nickname;
        }

        if ($realname) {
            $member->realname = $realname;
        }

        $bool = $member->save();

        if ($bool) {
            return $this->successJson('更新成功', $member);
        }


        return $this->errorJson('更新失败', ['error_code' => 'USER500', 'error_msg' => '数据库更新数据失败']);

    }

    public function create()
    {

        $this->validate([
            'mobile' => 'required',
            'password' => 'required',
        ]);

        $mobile =  trim(request()->input('mobile'));

        $password = request()->input('password');

        $registerInfo = request()->only(['nickname','realname']);

        //判断是否已注册
        $member_info =  Member::select('uid')
            ->where('uniacid', \YunShop::app()->uniacid)
            ->where('mobile', $mobile)->first();

        if (!empty($member_info)) {
            throw new ApiException('该手机号已被注册',['error_code' => 'USER501', 'error_msg' => '该手机号已被注册']);
        }


        $mid = intval(request()->input('mid'));
        if ($mid && is_null(Member::where('uid', $mid)->first())) {
            throw new ApiException('上级UID不存在',['error_code' => 'USER501']);
        }

        $member = DB::transaction(function () use ($mobile,$password, $registerInfo) {

            $member = CreateMemberService::createMember($mobile, $password,$registerInfo);

            return $member;
        });

        return $this->successJson('添加会员成功', $member);
    }
}