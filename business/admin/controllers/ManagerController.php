<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2021/9/22
 * Time: 13:37
 */

namespace business\admin\controllers;

use app\common\models\Member;
use business\common\models\ManagerList;
use business\common\services\BusinessService;
use business\common\controllers\components\BaseController;
use business\common\models\Business as BusinessModel;

class ManagerController extends BaseController
{

    /*
     * 管理员列表
     */
    public function managerList()
    {
        if (!$business_id = \request()->business_id) {
            return $this->errorJson('请选择要操作的企业');
        }

        if (!$this->checkManagerRight($business_id, 0, 1)) {
            return $this->errorJson('无权查看管理者列表');
        }

        $manager = BusinessService::getManager($business_id)->toArray(); //获取管理员列表
        $creater = BusinessService::getBusinessCreater($business_id); //获取企业创建人
        $owner = BusinessService::getBusinessOwner($business_id); //获取企业法人


        $return_data = [];

        foreach ($manager as $k => $v) {
            if ($creater->uid == $v['uid'] || $owner->uid == $v['uid']) {
                continue;
            }
            $return_data[] = [
                'id' => $v['id'],
                'uid' => $v['has_one_member']['uid'],
                'avatar' => $v['has_one_member']['avatar_image'] ?: '',
                'nickname' => $v['has_one_member']['username'] ?: '',
                'realname' => $v['has_one_member']['realname'] ?: '',
                'identity' => 2,
                'identity_desc' => '管理员',
            ];
        }


        if ($creater && $owner && $creater->uid == $owner->uid) {
            array_unshift($return_data, [
                'id' => 0,
                'uid' => $creater->uid,
                'avatar' => $creater->avatar_image ?: '',
                'nickname' => $creater->username ?: '',
                'realname' => $creater->realname ?: '',
                'identity' => 5,
                'identity_desc' => '法人、创建人',
            ]);
        } elseif ($creater) {
            array_unshift($return_data, [
                'id' => 0,
                'uid' => $creater->uid,
                'avatar' => $creater->avatar_image ?: '',
                'nickname' => $creater->username ?: '',
                'realname' => $creater->realname ?: '',
                'identity' => 3,
                'identity_desc' => '创建人',
            ]);
        } elseif ($owner) {
            array_unshift($return_data, [
                'id' => 0,
                'uid' => $owner->uid,
                'avatar' => $owner->avatar_image ?: '',
                'nickname' => $owner->username ?: '',
                'realname' => $owner->realname ?: '',
                'identity' => 4,
                'identity_desc' => '法人',
            ]);
        }

        return $this->successJson('获取成功', ['list' => $return_data]);
    }


    /*
     * 转让企业
     */
    public function changeBusinessOwner()
    {
        if (!$business_id = \request()->business_id) {
            return $this->errorJson('请选择要操作的企业');
        }

        if (!$business = BusinessModel::uniacid()->find($business_id)) {
            return $this->errorJson('企业不存在');
        }

        if (!$this->checkManagerRight($business_id, 0, 1)) {
            return $this->errorJson('无权转让企业');
        }

        if (!$member = Member::uniacid()->where('mobile', \request()->mobile)->first()) {
            return $this->errorJson('转让会员不存在');
        }

        $business->member_uid = $member->uid;
        $business->save();

        //清除相关人员的缓存
        BusinessService::flush(0, [\YunShop::app()->getMemberId(), $member->uid]);
        return $this->successJson('转让成功');

    }

    /*
     * 添加管理员
     */
    public function addManager()
    {

        if (!$business_id = \request()->business_id) {
            return $this->errorJson('请选择要操作的企业');
        }

        if (!$this->checkManagerRight($business_id, 0, 1)) {
            return $this->errorJson('无权添加管理员');
        }


        if (!$member = Member::uniacid()->where('mobile', \request()->mobile)->first()) {
            return $this->errorJson('不存在此用户');
        }

        $auth = BusinessService::checkBusinessRight($business_id, $member->uid, 1);
        if ($auth['identity'] > 1) {
            return $this->errorJson('该用户已经拥有管理员权限');
        } elseif (!$auth['identity']) {
            return $this->errorJson('请先将该用户添加为企业员工');
        }

        $manager = ManagerList::create([
            'uniacid' => \YunShop::app()->uniacid,
            'uid' => $member->uid,
            'business_id' => $business_id,
        ]);

        if (!$manager) {
            return $this->errorJson('创建管理员失败');
        }

        BusinessService::flush(0, $manager->uid);//清除会员缓存

        return $this->successJson('创建管理员成功');
    }


    /*
    * 删除管理员
    */
    public function deleteManager()
    {
        if (!$business_id = \request()->business_id) {
            return $this->errorJson('请选择要操作的企业');
        }

        if (!$this->checkManagerRight($business_id, 0, 1)) {
            return $this->errorJson('无权删除管理员');
        }

        if (!$manager = ManagerList::business($business_id)->find(\request()->id)) {
            return $this->errorJson('管理员不存在或已删除');
        }

        $delete_uid = $manager->uid;

        $manager->delete();

        BusinessService::flush(0, $delete_uid);//清除会员缓存

        return $this->successJson('删除成功');


    }


    private function checkManagerRight($business_id = 0, $member_id = 0, $forget = 0)
    {
        $premission = BusinessService::checkBusinessRight($business_id, $member_id, $forget);
        return $premission['identity'] >= 3;
    }

}
