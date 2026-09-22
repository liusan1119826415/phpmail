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

use app\common\helpers\QrCodeHelper;
use app\common\models\Member;
use business\common\models\Department;
use business\common\models\Staff;
use business\common\services\BusinessService;
use business\common\services\DepartmentService;
use business\common\services\SettingService;
use business\common\services\StaffService;
use business\common\controllers\components\BaseController;

class StaffController extends BaseController
{
    /*
     * 根据手机号查询会员
     */
    public function businessGetMemberByMobile()
    {
        $type = request()->search_type;
        $search = request()->search;
        $mobile = request()->mobile;
        if (!$mobile && !in_array($type, ['uid', 'mobile', 'strict_mobile'])) {
            return $this->errorJson('请输入搜索条件');
        }
        $query = Member::uniacid()->select('uid', 'avatar', 'nickname', 'realname', 'mobile');
        switch ($type) {
            case 'uid' :
                $query->where('uid', $search);
                break;
            case 'strict_mobile':
                $query->where('mobile', $search);
                break;
        }
        if (request()->mobile || $type == 'mobile') {
            $query->where('mobile', 'like', '%' . (request()->mobile ?: $search) . '%');
        }
        $member = $query->get();
        return $this->successJson('成功', $member);
    }

    /*
     * 查找企业员工
     */
    public function searchStaff()
    {
        $request = \request();
        $kwd = $request->kwd;
        $where = [
            [function ($query) use ($kwd) {
                $query->where('name', 'like', "%{$kwd}%")->orWhere('mobile', 'like', "%{$kwd}%");
            }]
        ];

        if ($request->is_qy_wx) { //是否只查询企业微信员工
            $where[] = ['status', '<>', 0];
        }

        if ($request->disabled) { //是否屏蔽禁用员工
            $where[] = ['disabled', 0];
        }

        $staff = Staff::business()->where($where)->get();

        if ($staff->isNotEmpty()) {
            $staff = $staff->map(function ($v) {
                $v->gender_desc = Staff::GENDER_DESC[$v->gender] ?: '未知';
                $v->status_desc = Staff::STATUS_DESC[$v->status] ?: '未知';
                return $v;
            });
        }

        return $this->successJson('成功', $staff);
    }


    /*
     * 创建员工
     */
    public function createStaff()
    {
        if (\request()->id) {
            return $this->errorJson('参数异常');
        }

        $check_res = StaffService::checkManageRightByKey('createStaff', \request()->id, \request()->department_id);
        if (!$check_res['result']) {
            return $this->errorJson($check_res['msg']);
        }

        if (request()->department_id) {
            $true_request_department_id = [];
            $request_department_id = request()->department_id;
            foreach ($request_department_id as $v) {
                $true_request_department_id[] = end($v);
            }
            request()->offsetSet('department_id', $true_request_department_id);
        }

        $res = StaffService::changeStaff(\request()); //推送
        if (!$res['result']) {
            return $this->errorJson($res['msg']);
        }

        if (SettingService::EnabledQyWx() && $staff = $res['data']['staff']) {
            $res = StaffService::refreshStaff(0, $staff->id); //同步
            if (!$res['result']) {
                return $this->errorJson($res['msg']);
            }
        }

        return $this->successJson('创建成功');
    }


    /*
     * 编辑员工
     */
    public function updateStaff()
    {

        $check_res = StaffService::checkManageRightByKey('updateStaff', \request()->id, \request()->department_id);
        if (!$check_res['result']) {
            return $this->errorJson($check_res['msg']);
        }

        if (!\request()->id) {
            return $this->errorJson('请选择要编辑的员工');
        }

        if (!\request()->is_edit) {

            $staff = Staff::business()->select('id', 'uid', 'name', 'mobile', 'telephone', 'email', 'avatar', 'alias', 'address', 'position')
                ->with(['hasOneMember' => function ($query) {
                    $query->select('uid', 'avatar', 'nickname', 'realname', 'mobile');
                }])->with(['hasManyDepartmentStaff' => function ($query) {
                    $query->with('hasOneDepartment');
                }])->find(\request()->id);

            if (!$staff) {
                return $this->errorJson('员工不存在');
            }

            $department_list = [];
            $department_id = [];
            $department_name = [];
            $staff->hasManyDepartmentStaff->each(function ($v) use (&$department_list) {
                if ($v->hasOneDepartment->id) {
                    if (!$department_list[$v->hasOneDepartment->level]) $department_list[$v->hasOneDepartment->level] = collect([]);
                    $department_list[$v->hasOneDepartment->level]->push(['id' => $v->hasOneDepartment->id, 'name' => $v->hasOneDepartment->name, 'order' => $v->hasOneDepartment->order]);
                }
            });
            ksort($department_list);
            foreach ($department_list as $v) {
                $v = $v->sortByDesc('order');
                $department_id = array_merge($department_id, $v->pluck('id')->all());
                $department_name = array_merge($department_name, $v->pluck('name')->all());
            }

            $all_department = Department::business()->get();
            $true_department_id = [];
            foreach ($department_id as $v) {
                $this_parent_department = Department::getAllDepartmentParentId($v, $all_department);
                $this_parent_department = array_reverse($this_parent_department);
                $this_parent_department[] = $v;
                $true_department_id[] = $this_parent_department;
            }

            $staff->setAttribute('department_id', $true_department_id);
            $staff->setAttribute('department_name', $department_name);
            $staff->unsetRelation('hasManyDepartmentStaff');

            return $this->successJson('获取成功', ['staff' => $staff]);
        }

        if (request()->department_id) {
            $true_request_department_id = [];
            $request_department_id = request()->department_id;
            foreach ($request_department_id as $v) {
                $true_request_department_id[] = end($v);
            }
            request()->offsetSet('department_id', $true_request_department_id);
        }

        $res = StaffService::changeStaff(\request());
        if (!$res['result']) {
            return $this->errorJson($res['msg']);
        }

        if (SettingService::EnabledQyWx() && $staff = $res['data']['staff']) {
            $res = StaffService::refreshStaff(0, $staff->id); //同步
            if (!$res['result']) {
                return $this->errorJson($res['msg']);
            }
        }

        return $this->successJson('修改成功');
    }

    /*
     * 删除员工
     */
    public function deleteStaff()
    {
        $check_res = StaffService::checkManageRightByKey('deleteStaff', \request()->id, []);
        if (!$check_res['result']) {
            return $this->errorJson($check_res['msg']);
        }
        $disabled = request()->disabled ? 0 : 1;
        $res = StaffService::deleteStaff(\request()->id, $disabled);
        if (!$res['result']) {
            return $this->errorJson($res['msg']);
        }
        return $this->successJson($disabled ? '禁用成功' : '解禁成功');

    }

    /*
    * 推送员工信息到企业微信
    */
//    public function pushStaff()
//    {
//        $res = StaffService::pushStaff(\request()->id);
//        if (!$res['result']) {
//            return $this->errorJson($res['msg']);
//        }
//        return $this->successJson('推送成功');
//    }

    /*
     * 设置部门领导
     */
    public function setDepartmentLeader()
    {

        $right = BusinessService::checkBusinessRight();
        if (!$right['identity'] || $right['identity'] < 2) {
            return $this->errorJson('无权设置部门主管');
        }

        $res = StaffService::setStaffLeader(\request()->department_id, \request()->staff_id);
        if (!$res['result']) {
            return $this->errorJson($res['msg']);
        }
        return $this->successJson('设置成功');
    }

    /*
     * 从企业微信同步员工列表
     */
    public function refreshStaffList()
    {

        if (!SettingService::EnabledQyWx()) {
            return $this->errorJson('未开启企业微信', ['is_set' => 1]);
        }

        if ($department_id = \request()->department_id) {
            $department = Department::business()->where('wechat_department_id', '<>', 0)->find($department_id);
        } else {
            $department = Department::business()->where('level', 1)->where('wechat_department_id', '<>', 0)->first();
        }

        if (!$department) {
            return $this->errorJson('部门不存在或未关联企业微信');
        }

        $res = StaffService::refreshStaff($department->id);
        if (!$res['result']) {
            return $this->errorJson($res['msg']);
        }

        return $this->successJson('同步成功');
    }

    /*
     * 根据部门id获取员工列表
     */
    public function getStaffList()
    {
        $request = request();
//        if (!DepartmentService::checkDepartmentIdByMethod('getStaffList', $request->department_id)) {
//            return $this->errorJson('暂无权限查看此部门员工');
//        }
//
//        if (!$department = Department::business()->find($request->department_id)) {
//            return $this->errorJson('部门不存在');
//        }
        // 需求改成显示当前部门下(包括下级)所有可显示的员工
        $department_ids = DepartmentService::getDepartmentIds($request->department_id ?: 0, true);
        if ($request->department_id and DepartmentService::checkDepartmentIdByMethod('getStaffList', $request->department_id)) {
            $department_ids[] = (int)$request->department_id;
        }

        if (!$request->sub_page) {
            $res = StaffService::getStaffList($department_ids, [
                'kwd' => trim(\request()->kwd),
                'id' => intval(\request()->id) ?: 0,
                'status' => \request()->status,
                'disabled' => \request()->disabled,
            ])->paginate()->toArray();
        } else {
            $list = StaffService::getStaffList($department_ids, [
                'kwd' => trim(\request()->kwd),
                'id' => intval(\request()->id) ?: 0,
                'status' => \request()->status,
                'disabled' => \request()->disabled,
            ])->get()->toArray();
            $res['data'] = $list;
        }

        StaffService::addStaffPremission($res['data']);

        $return_list = [];
        $leader_list = [];
        foreach ($res['data'] as $v) {
            $return_list[] = $this_data = [
                'id' => $v['staff_id'],
                'uid' => $v['has_one_staff']['uid'] ?: 0,
                'name' => $v['has_one_staff']['name'] ?: '',
                'mobile' => $v['has_one_staff']['mobile'] ?: '',
                'position' => $v['has_one_staff']['position'] ?: '',
                'gender_desc' => Staff::GENDER_DESC[$v['has_one_staff']['gender']] ?: '未知',
                'status' => Staff::STATUS_DESC[$v['has_one_staff']['status'] ?: 0],
//                'status' => $v['has_one_staff']['status'] ?: 0,
                'disabled' => $v['has_one_staff']['disabled'] ? 1 : 0,
                'premission' => $v['premission'],
                'has_one_member' => $v['has_one_staff']['has_one_member'] ?: null
            ];
            if ($request->get_leader && $v['is_leader']) {
                $leader_list[] = $this_data;
            }
        }
        $res['data'] = $return_list;
        if ($request->get_leader) {
            $res['leader_list'] = $leader_list ? array_column($leader_list, null, 'id') : [];
        }

        $res['premission']['createStaff'] = BusinessService::getPremissionByRoute('createStaff');
        $res = array_merge($res, SettingService::getStaffTicketCodeUrl());
        return $this->successJson('成功', $res);
    }

}
