<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/4/19
 * Time: 17:08
 */

namespace app\outside\modules\member\services;

use app\backend\modules\member\models\MemberRecord;
use app\common\exceptions\ApiException;
use Illuminate\Support\Str;
use app\common\helpers\Url;
use app\common\models\MemberGroup;
use app\frontend\modules\member\models\SubMemberModel;
use app\frontend\modules\member\models\MemberModel;
use app\backend\modules\charts\modules\phone\models\PhoneAttribution;
use app\backend\modules\charts\modules\phone\services\PhoneAttributionService;

class CreateMemberService
{

    /**
     * @param $mobile int 手机号
     * @param $password string 密码
     * @param array $registerInfo 注册会员数据
     * @return MemberModel
     * @throws ApiException
     */
    public static function createMember($mobile,$password,$registerInfo = [])
    {
        //会员分组
        $defaultGroup = MemberGroup::getDefaultGroupId()->first();

        //获取图片
        $member_set = \Setting::get('shop.member');
        if (isset($member_set) && $member_set['headimg']) {
            $avatar = yz_tomedia($member_set['headimg']);
        } else {
            $avatar = Url::shopUrl('static/images/photo-mr.jpg');
        }

        $createData = array_merge([
            'groupid' => $defaultGroup->id ? $defaultGroup->id : 0,
            'mobile' => $mobile,
            'nickname' => $mobile,
            'avatar' => $avatar,
        ], $registerInfo);

        $memberModel = self::saveMcMember($createData,$password);

        $member_id = $memberModel->uid;


        if ($memberModel->mobile) {
            self::phoneAttribution($member_id,$memberModel->mobile);
        }

        self::saveMemberInfo($member_id,$defaultGroup->id?:0);

        //记录
        MemberRecord::create(['uid' => $member_id, 'uniacid' => \YunShop::app()->uniacid,
            'parent_id' => \app\common\models\Member::getMid(), 'after_parent_id' => 0, 'status' => 1,
        ]);

        //生成分销关系链，暂时不支持填邀请码
        \app\common\models\Member::createRealtion($member_id);

        return $memberModel;
    }

    public static function phoneAttribution($member_id,$mobile)
    {
        //手机归属地查询插入
        $phoneData = file_get_contents((new PhoneAttributionService())->getPhoneApi($mobile));
        $phoneArray = json_decode($phoneData);
        $phone['uid'] = $member_id;
        $phone['uniacid'] =  \YunShop::app()->uniacid;
        $phone['province'] = $phoneArray->data->province;
        $phone['city'] = $phoneArray->data->city;
        $phone['sp'] = $phoneArray->data->sp;
        $phoneModel = new PhoneAttribution();
        $phoneModel->updateOrCreate(['uid' => $member_id], $phone);
    }

    /**
     * @param $data
     * @return MemberModel
     * @throws ApiException
     */
    public static function saveMcMember($data,$password = null)
    {

        if (!$password) {
            $password =  Str::random(6);
        }
        //随机数
        $data['salt'] = Str::random(8);
        //加密
        $data['password'] = md5($password . $data['salt']);

        $memberModel = new MemberModel([
            'uniacid' => \YunShop::app()->uniacid,
            'createtime' => time(),
            'gender' => 0,
            'residecity' => '',
        ]);

        $memberModel->fill($data);

        $bool = $memberModel->save();

        if (!$bool) {
            throw new ApiException('创建保存会员主表失败',['error_code' => 'USER500']);
        }

        return $memberModel;
    }

    /**
     * @param $member_id
     * @param $default_group_id
     * @return SubMemberModel
     * @throws ApiException
     */
    public static function saveMemberInfo($member_id, $default_group_id)
    {
        $shopMember = new SubMemberModel();

        $shopMember->fill([
            'member_id' => $member_id,
            'group_id' => $default_group_id,
            'uniacid' => \YunShop::app()->uniacid,
            'invite_code' => MemberModel::getUniqueInviteCode(),
            'system_type' => 'api',
            'level_id' => 0,
            'pay_password' => '',
            'salt' => '',
        ]);

        $bool = $shopMember->save();

        if (!$bool) {
            throw new ApiException('创建保存商城会员表失败',['error_code' => 'USER500']);
        }

        return $shopMember;
    }
}