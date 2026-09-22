<?php
/**
 * Created by PhpStorm.
 * Date: 9/12/23
 * Time: 10:18 AM
 */

namespace app\frontend\modules\member\services;


use app\common\exceptions\ShopException;
use app\common\facades\Setting;
use app\common\helpers\Url;
use app\common\services\Session;
use app\frontend\models\Member;
use app\frontend\models\MemberShopInfo;
use app\frontend\modules\member\models\MemberModel;
use app\frontend\modules\member\models\MemberStateGrid;
use app\frontend\modules\member\models\SubMemberModel;
use Illuminate\Support\Str;

class MemberStateGridService extends MemberService
{
    public function login()
    {
        if (!$this->pluginStatus()) {
            return [];
        }
        if ($uid = $this->verify(request()->input())) {
            $data['uid'] = $uid;
            return show_json(1, $data);
        }
        return [];
    }

    /**
     * 验证登录状态
     * @return bool
     * @throws ShopException
     */
    public function checkLogged()
    {
        $member = null;
        $member_id = \YunShop::app()->getMemberId();
        if ($member_id) {
            $member = Member::getMemberByUid($member_id)->first();
            if ($member) {
                return true;
            }
        }
        return false;
    }
    /**
     * @param $data
     * @return bool
     * @throws ShopException
     */
    public function verify($data)
    {
        if (!$data['mobile']&&!$data['id']) {
            return false;
        }
        $exist = MemberStateGrid::uniacid()->where('encryption_pwd', md5($data['mobile'].$data['id']))->first();
        if ($exist) {
            $uid = $exist->member_id;
            Session::set('member_id', $uid);
            return $uid;
        } else {
            $uid = $this->addMcMember($data);
            $this->addYzMember($uid, \YunShop::app()->uniacid);
            $this->addFans($data, $uid);
            return $uid;
        }
    }

    private function pluginStatus()
    {
        $plugin_set = Setting::get('member_state_grid');
        if ($plugin_set['is_open']&&app('plugins')->isEnabled('member-state-grid')) {
            return true;
        }
        return false;
    }

    private function addFans($data, $uid)
    {
        MemberStateGrid::create([
            'uniacid' => \YunShop::app()->uniacid,
            'member_id' => $uid,
            'avatar' => $data['avatar'],
            'channel' => $data['channel'],
            'channel_type' => $data['channelType'],
            'email' => $data['email'],
            'enabled' => $data['enabled']?:0,
            'extra' => json_encode($data['extra']),
            'out_id' => $data['id'],
            'is_online' => $data['isOnline']?:'',
            'last_login_at' => $data['lastLoginAt'],
            'locked' => $data['locked']?:0,
            'mobile' => $data['mobile'],
            'nickname' => $data['nickname'],
            'origin' => $data['origin'],
            'password_exist' => $data['passwordExist'],
            'pk' => $data['pk'],
            'prefix' => $data['prefix'],
            'pwd_expired' => $data['pwdExpired'],
            'source' => $data['source'],
            'source_type' => $data['sourceType'],
            'tag' => $data['tag'],
            'tenant_id' => $data['tenantId'],
            'created_at_' => $data['createdAt'],
            'updated_at_' => $data['updatedAt'],
            'user_detail' => json_encode($data['userDetail']),
            'username' => $data['username'],
            'encryption_pwd' => md5($data['mobile'].$data['id']),
        ]);
    }

    private function addMcMember($data)
    {
        $member_model = new MemberModel();
        $member_model->uniacid    = \YunShop::app()->uniacid;
        $member_model->email      = '';
        $member_model->mobile     = $data['mobile'];
        $member_model->email     = $data['email'];
        $member_model->groupid    = 0;
        $member_model->createtime = time();
        $member_model->nickname   = stripslashes($data['nickname']);
        $member_model->avatar     = $data['avatar']?:Url::shopUrl('static/images/photo-mr.jpg');
        $member_model->gender         = -1;
        $member_model->nationality    = '';
        $member_model->resideprovince = '' . '省';
        $member_model->residecity     = '' . '市';
        $member_model->credit1        = 0;
        $member_model->credit2        = 0;
        $member_model->salt           = Str::random(8);
        $member_model->password       = md5(mt_rand());
        if ($member_model->save()) {
            return $member_model->uid;
        } else {
            return 0;
        }
    }

    private function addYzMember($member_id, $uniacid)
    {
        SubMemberModel::insertData(array(
            'member_id'    => $member_id,
            'uniacid'      => $uniacid,
            'group_id'     => 0,
            'level_id'     => 0,
            'pay_password' => '',
            'salt'         => '',
            'yz_openid'    => '',
        ));
    }
}