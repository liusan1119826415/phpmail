<?php
/**
 * Created by PhpStorm.
 * 
 *
 *
 * Date: 2021/9/22
 * Time: 13:37
 */


namespace business\common\services;

use app\backend\modules\member\models\Member;
use business\common\models\Department;
use business\common\models\DepartmentStaff;
use business\common\models\Staff;
use business\common\models\StaffTicket;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Yunshop\WorkWechat\common\models\Employee;

class StaffService
{


    /*
     * 校验成员管理权限
     */
    public static function checkManageRightByKey($function, $staff_id, $department_id_list)
    {
        $function_arr = [
            'updateStaff' => '编辑',
            'createStaff' => '创建',
            'deleteStaff' => '禁用'
        ];

        $right = BusinessService::checkBusinessRight();

        if ($right['identity'] > 1) {
            return ['result' => 1, 'msg' => '管理员权限无需校验'];
        }

        if (!$right_department_id = $right['route_department_id'][$function]) {
            return ['result' => 0, 'msg' => '无权' . $function_arr[$function] . '员工'];
        }

        $isset_department_id = DepartmentStaff::where('staff_id', $staff_id)->pluck('department_id')->toArray();
        $department_list = array_column(Department::business()->get()->toArray(), null, 'id');

        if ($function == 'updateStaff' || $function == 'deleteStaff') {
            if (empty(array_intersect($right_department_id, $isset_department_id))) {
                return ['result' => 0, 'msg' => '无权' . $function_arr[$function] . '当前员工'];
            }
        }

        if (($function == 'updateStaff' && request()->is_edit == 1) || $function == 'createStaff') {
            $add_department_id = array_diff($department_id_list, $isset_department_id);
            $delete_department_id = array_diff($isset_department_id, $department_id_list);

            if ($add_diff_id = array_diff($add_department_id, $right_department_id)) {
                foreach ($add_diff_id as $v) {
                    if (!$department_list[$v]) {
                        continue;
                    } else {
                        return ['result' => 0, 'msg' => '无权将当前员工加入' . $department_list[$v]['name']];
                    }
                }
            }

            if ($delete_diff_id = array_diff($delete_department_id, $right_department_id)) {
                foreach ($delete_diff_id as $v) {
                    if (!$department_list[$v]) {
                        continue;
                    } else {
                        return ['result' => 0, 'msg' => '无权将当前员工移出' . $department_list[$v]['name']];
                    }
                }
            }
        }

        return ['result' => 1, 'msg' => '校验通过'];

    }


    /*
     * 禁用/解禁部门成员
     */
    public static function deleteStaff($staff_id, $disabled = 1)
    {
        try {

            if (!$staff_id) {
                throw new Exception('请选择要禁用的员工');
            }

            $where[] = is_array($staff_id) ? [function ($query) use ($staff_id) {
                $query->whereIn('id', $staff_id);
            }] : ['id', $staff_id];
            $count = is_array($staff_id) ? count($staff_id) : 1;
            $staff = Staff::business()->where($where)->get();
            $clean_member_id = [];

            if ($staff->count() != $count) {
                throw new Exception('员工不存在');
            }

            DB::beginTransaction();
            $staff->each(function ($v) use ($disabled, &$clean_member_id) {
                if ($v->uid) $clean_member_id[] = $v->uid;
                Staff::where('id', $v->id)->update(['disabled' => $disabled]);
            });


        } catch (Exception $e) {
            DB::rollBack();
            return self::returnArr(0, $e->getMessage());
        }

        DB::commit();

        if ($clean_member_id) {
            BusinessService::flush(0, $clean_member_id); //清除会员缓存
        }
        return self::returnArr(1, '禁用成功');
    }

    /*
     * 设置部门领导
     */
    public static function setStaffLeader($department_id, $staff_id = [])
    {
        try {

            if (!$department = Department::business()->find($department_id)) {
                throw new Exception('部门不存在');
            }


            $where = [
                ['department_id', $department->id],
                ['is_leader', 1]
            ];

            if ($staff_id) {
                $staff_arr = DepartmentStaff::business()->where('department_id', $department->id)
                    ->whereIn('staff_id', $staff_id)
                    ->with('hasOneStaff')
                    ->groupBy('staff_id', 'department_id')
                    ->get()->toArray();

                if (count($staff_arr) != count($staff_id)) {
                    throw new Exception('存在员工未加入此部门');
                }

                $where[] = [function ($query) use ($staff_id) {
                    $query->whereNotIn('staff_id', $staff_id);
                }];
            }
            $msg = [];

            if ($department->wechat_department_id && SettingService::EnabledQyWx()) {

                $delete_staff = DepartmentStaff::business()->where($where)->with('hasOneStaff')->get()->toArray();
                if ($delete_staff) {
                    foreach ($delete_staff as $v) {
                        if (empty($v['has_one_staff']['status'])) {
                            continue;
                        }
                        DB::beginTransaction();
                        DepartmentStaff::where('id', $v['id'])->update(['is_leader' => 0]);
                        $res = self::pushStaff($v['staff_id']);
                        if (!$res['result']) {
                            DB::rollBack();
                            $msg[] = '员工' . $v['has_one_staff']['name'] . '企业微信推送,' . $res['msg'];
                            continue;
                        }
                        DB::commit();
                    }
                }

                foreach ($staff_arr as $v) {
                    if (empty($v['has_one_staff']['status'])) {
                        $msg[] = "员工{$v['has_one_staff']['name']}未与企业微信同步";
                        continue;
                    }
                    DB::beginTransaction();
                    DepartmentStaff::where('id', $v['id'])->update(['is_leader' => 1]);
                    $res = self::pushStaff($v['staff_id']);
                    if (!$res['result']) {
                        DB::rollBack();
                        $msg[] = '员工' . $v['has_one_staff']['name'] . '企业微信推送,' . $res['msg'];
                        continue;
                    }
                    DB::commit();
                }

                if ($msg) {
                    throw new Exception(implode(',', $msg));
                }
            } else {
                DepartmentStaff::business()->where($where)->update(['is_leader' => 0]);
                DepartmentStaff::business()->whereIn('staff_id', $staff_id)->where('department_id', $department->id)->update(['is_leader' => 1]);
            }
        } catch (Exception $e) {
            return self::returnArr(0, $e->getMessage());
        }

        BusinessService::flush(SettingService::getBusinessId()); //清除企业缓存
        return self::returnArr(1, '设置部门领导成功');
    }


    /*
     * 创建/编辑 员工
     */
    public static function changeStaff($data)
    {

        DB::beginTransaction();
        try {

            $business_id = SettingService::getBusinessId();

            if (!$data->mobile || !$data->name) {
                throw new Exception('手机、姓名不能为空');
            }

            $staff = null;
            if ($data->id && (!$staff = Staff::business()->find($data->id))) {
                throw new Exception('员工不存在');
            }

            if ($data->uid) {
                if (!$member = Member::uniacid()->find($data->uid)) {
                    throw new Exception('系统会员不存在');
                }
                if (!$member->mobile) {
                    throw new Exception('系统会员未绑定手机号');
                }
                if ($data->mobile != $member->mobile) {
                    throw new Exception('系统会员手机号与员工手机号不一致');
                }
            }

            /*判断是否有手机号或绑定系统会员冲突的员工*/
            $where = [
                ['business_id', $business_id],
                [function ($query) use ($member) {
                    $query->where('uid', $member->uid)->orWhere('mobile', $member->mobile);
                }],
            ];
            if ($staff) {
                $where[] = ['id', '<>', $staff->id];
            }
            $isset_staff = Staff::uniacid()->where($where)->first();

            if ($isset_staff) {
                throw new Exception($isset_staff->uid == $member->uid ? '系统会员已经绑定其他员工' : '系统会员手机号与已存在员工重复');
            }
            /*判断是否有手机号或绑定系统会员冲突的员工*/

            $name = trim($data->name);
            $user_id = $staff ? $staff->user_id : self::overTrue($name, 21) . '_' . time();
            $update_data = [
                'user_id' => $user_id,
                'name' => $name ?: '',
                'uid' => $member->uid,
                'mobile' => $member->mobile ?: '',
                'position' => trim($data->position) ?: '',
                'gender' => in_array($data->gender, [0, 1, 2]) ?: 0,
                'telephone' => trim($data->telephone) ?: '',
                'email' => trim($data->email) ?: '',
                'avatar' => $data->avatar ?: '',
                'alias' => $data->alias ?: '',
                'address' => $data->address ?: '',
                'open_userid' => $business_id . '_' . $user_id,
            ];

            if ($staff) {
                if (SettingService::EnabledQyWx() && !in_array($staff->status, [0, 4]) && $staff->mobile != $update_data['mobile']) {
                    throw new Exception('该成员已激活企业微信,需自行修改手机号');
                }
                $staff->fill($update_data);
                $staff->save();
            } else {
                $staff = Staff::create(array_merge($update_data, [
                    'uniacid' => \YunShop::app()->uniacid,
                    'business_id' => $business_id,
                ]));
            }

            if (!$department_id = is_array($data->department_id) && $data->department_id ? $data->department_id : []) {
                throw new Exception('请选择成员所属部门');
            }

            $department_list = Department::business()->get();
            if ($department_list->whereIn('id', $department_id)->isEmpty()) {
                throw new Exception('请选择有效部门');
            }

            $department_staff_list = DepartmentStaff::uniacid()->where('staff_id', $staff->id)->get()->toArray();
            $department_list = $department_list->isNotEmpty() ? array_column($department_list->toArray(), null, 'id') : [];
            $department_staff_list = $department_staff_list ? array_column($department_staff_list, null, 'department_id') : [];

            foreach ($department_id as $k => $v) {

                if (!$this_department = $department_list[$v]) {
                    throw new Exception('部门不存在');
                }

                if (!$this_department_staff = $department_staff_list[$this_department['id']]) {
                    DepartmentStaff::create([
                        'uniacid' => \YunShop::app()->uniacid,
                        'staff_id' => $staff->id,
                        'business_id' => $business_id,
                        'department_id' => $this_department['id'],
                        'is_leader' => 0,
                        'sort' => 1,
                    ]);
                }
            }

            foreach ($department_staff_list as $v) {
                if (!in_array($v['department_id'], $department_id) || !isset($department_list[$v['department_id']])) {
                    DepartmentStaff::where('id', $v['id'])->delete();
                }
            }

            //企业微信开启 并且 员工不是未关联且被禁用 时，进行同步
            if (SettingService::EnabledQyWx() && ($staff->disabled != 1)) {
                $push_res = self::pushStaff($staff->id);
                if (!$push_res['result']) {
                    throw new Exception($push_res['msg']);
                }
                if ($staff->status == 0) {
                    $staff->status = 4;
                    $staff->save();
                }
            }


        } catch (Exception $e) {
            DB::rollBack();
            return self::returnArr(0, $e->getMessage());
        }

        DB::commit();

        if ($staff->uid) {
            BusinessService::flush(0, $staff->uid);
        }

        return self::returnArr(1, '成功', ['staff' => $staff]);

    }


    /*
     * 推送成员信息到企业微信
     */
    public static function pushStaff($staff_id)
    {

        try {

            if (!SettingService::EnabledQyWx()) {
                throw new Exception('未开启企业微信同步');
            }

            $staff = Staff::business()
                ->with(['hasManyDepartmentStaff' => function ($query) {
                    $query->with('hasOneDepartment');
                }])->find($staff_id);

            if (!$staff) {
                throw new Exception('员工不存在');
            }

            $department_id_arr = [];
            $department_order_arr = [];
            $department_leader_arr = [];

            if ($staff->hasManyDepartmentStaff->isNotEmpty()) {
                $staff->hasManyDepartmentStaff->each(function ($v) use (&$department_id_arr, &$department_order_arr, &$department_leader_arr) {
                    if ($v->hasOneDepartment->wechat_department_id) {
                        $department_id_arr[] = $v->hasOneDepartment->wechat_department_id;
                        $department_order_arr[] = intval($v->order) ?: 1;
                        $department_leader_arr[] = $v->is_leader ? 1 : 0;
                    }
                });
            }

            $request_data = Staff::getQyWxStaff($staff, $department_id_arr, $department_order_arr, $department_leader_arr);

            $res = (new QyWechatRequestService())->request($staff->status == 0 ? 'createMember' : 'updateMember', $request_data);
            if (!$res['result']) {
                throw new Exception($res['msg']);
            }

        } catch (Exception $e) {
            return self::returnArr(0, $e->getMessage());
        }

        return self::returnArr(1, '推送成员成功');
    }


    /*
    * 从企业微信同步成员
    */
    public static function refreshStaff($department_id = 0, $staff_id = 0, $wechat_user_id = '')
    {
        if (!$business_id = SettingService::getBusinessId()) {
            return self::returnArr(0, '请先选择要管理的企业');
        }

        $res = self::getWechatStaffList($department_id, $staff_id, $wechat_user_id);

        if (!$res['result']) {
            return $res;
        }
        $staff_list = $res['data'];

        $wechat_department = Department::getWechatBusinessDepartment($business_id); //获取该企业已经关联企业微信部门

        //获取员工与企业微信部门的关联信息
        $department_staff_list = $wechat_department->isNotEmpty() ? DepartmentStaff::uniacid()->select('staff_id', 'department_id', 'is_leader')->whereIn('department_id', array_column($wechat_department->toArray(), 'id'))->get() : collect((object)[]);
        $department_staff_list = $department_staff_list->map(function ($v) use ($wechat_department) {
            if ($this_department_staff = $wechat_department->where('id', $v->department_id)->first()) {
                $v->wechat_department_id = $this_department_staff->wechat_department_id;
            }
            return $v;
        });
        $user_id_list = [];

//        $isset_staff = Staff::business()->get(); //获取该企业已经存在的员工
        if ($staff_list) {
            $insert_data = [];
            $mobile_list = Member::uniacid()->select('uid', 'mobile')->whereIn('mobile', array_values(array_filter(array_column($staff_list, 'mobile'))))->get()->toArray();
            $mobile_list = $mobile_list ? array_column($mobile_list, 'uid', 'mobile') : [];
            $time = time();
            $extra_columns = ['address', 'mobile', 'email', 'avatar', 'qr_code'];
            foreach ($staff_list as $k => $v) {
                $user_id_list[] = $v['userid'];
                $form_data = self::formWechatStaff($business_id, $v);
                if ($mobile_list[$v['mobile']]) {
                    $form_data['uid'] = $mobile_list[$v['mobile']];
                }
                //如果存在user_id相同的员工
                $this_staff = Staff::business()->where('user_id', $v['userid'])->first();
                if (!$this_staff && $v['mobile']) {
                    //如果存在手机号相同的员工
                    $this_staff = Staff::business()->where('mobile', $v['mobile'])->first();
                }

                if ($this_staff) {
                    if (self::checkRepeatStaff($form_data['uid'], $this_staff->id)) {
                        $form_data['uid'] = 0;
                    }
                    foreach ($extra_columns as $ec) {
                        if ($this_staff->$ec && !$form_data[$ec]) {
                            unset($form_data[$ec]);
                        }
                    }
                    $this_staff->fill($form_data);
                    $this_staff->save();
                } else { //否则新建员工
                    foreach ($extra_columns as $ec) {
                        $form_data[$ec] = $form_data[$ec] ?: '';
                    }
                    $form_data['mobile'] = $form_data['mobile'] ?: '';
                    $form_data['email'] = $form_data['email'] ?: '';
                    $key = 'create_staff_' . $form_data['business_id'] . '_' . $form_data['user_id'];
                    if (Redis::setnx($key, 1)) {
                        Redis::expire($key, 10);
                    } else {
                        continue;
                    }
                    $form_data['uniacid'] = \YunShop::app()->uniacid;
                    $form_data['uid'] = self::getConnectionMember($form_data, 1);
                    $form_data['mobile'] = $form_data['mobile'] ?: '';
                    $this_staff = Staff::create($form_data);
                }

                $delete_department_id = [];

                //获取该员工的部门关联
                $this_department_staff_list = $department_staff_list->isNotEmpty() ? $department_staff_list->where('staff_id', $this_staff->id)->toArray() : [];

                /*如果存在本地与微信部门关联，但返回的信息中不存在的，删除关联*/
                foreach ($this_department_staff_list as $vv) {
                    if (in_array($vv['wechat_department_id'], $v['department'])) {
                        continue;
                    }
                    if ($this_delete_department = $wechat_department->where('wechat_department_id', $vv['wechat_department_id'])->first()) {
                        $delete_department_id[] = $this_delete_department->id;
                    }
                }
                if ($delete_department_id) {
                    DepartmentStaff::where('staff_id', $this_staff->id)->whereIn('department_id', $delete_department_id)->delete();
                }
                /*如果存在本地与微信部门关联，但返回的信息中不存在的，删除关联*/

                //获取该员工本地已存在的微信部门关联
                $this_isset_department_id = $this_department_staff_list ? array_column($this_department_staff_list, null, 'wechat_department_id') : [];
                /*如果员工信息中存在  本地存在的微信部门 并且 未与员工关联的部门 ，新增关联*/
                foreach ($v['department'] as $kk => $vv) {
                    if (in_array($vv, array_keys($this_isset_department_id))) {
                        if ($this_isset_department_id[$vv]['is_leader'] != $v['is_leader_in_dept'][$kk]) {
                            DepartmentStaff::uniacid()->where('department_id', $this_isset_department_id[$vv]['department_id'])->where('staff_id', $this_staff->id)->update(['is_leader' => $v['is_leader_in_dept'][$kk] ? 1 : 0]);
                        }
                        continue;
                    }
                    if ($this_insert_department = $wechat_department->where('wechat_department_id', $vv)->first()) {
                        $insert_data[] = [
                            'uniacid' => \YunShop::app()->uniacid,
                            'business_id' => $business_id,
                            'staff_id' => $this_staff->id,
                            'department_id' => $this_insert_department->id,
                            'is_leader' => $v['is_leader_in_dept'][$kk] ? 1 : 0,
                            'sort' => intval($v['order'][$kk]),
                            'created_at' => $time,
                            'updated_at' => $time,
                        ];
                    }
                }
                /*如果员工信息中存在  本地存在的微信部门 并且 未与员工关联的部门 ，新增关联*/
            }

            if ($insert_data) {
                $insert_data = array_chunk($insert_data, 2000);
                foreach ($insert_data as $v) {
                    DepartmentStaff::insert($v);
                }
            }
        }

        self::cleanWechatDepartemntStaff($department_id, $user_id_list); //清除无关联员工
        BusinessService::flush(SettingService::getBusinessId()); //清除企业缓存
        return self::returnArr(1, '同步企业微信员工成功');

    }

    /*
     * 检测是否有重复绑定商城会员
     */
    public static function checkRepeatStaff($uid, $expect_staff_id)
    {
        if ($uid == 0) return false;
        return Staff::business()->where('uid', $uid)->where('id', '<>', $expect_staff_id)->first();
    }

    /*
     * 清除无关企业微信部门员工
     */
    public static function cleanWechatDepartemntStaff($department_id, $user_id_list)
    {
        if (!$department_id) return;
        if (!$staff_id = DepartmentStaff::where('department_id', $department_id)->groupBy('staff_id')->pluck('staff_id')->toArray()) return;

//        $sub_department_id = Department::getAllDepartmentSubId([$department_id]);
        $where = [
            ['uniacid', \YunShop::app()->uniacid],
            ['business_id', SettingService::getBusinessId()],
            ['status', '<>', 0],
            [function ($query) use ($staff_id) {
                $query->whereIn('id', $staff_id);
            }]
        ];
        if ($user_id_list) {
            $where[] = [function ($query) use ($user_id_list) {
                $query->whereNotIn('user_id', $user_id_list);
            }];
        }

        $staff_id_list = Staff::where($where)->pluck('id')->toArray();
        if ($staff_id_list) {  // 禁用会员中关联了部门且关联了企业微信，但企业微信返回数据中不存在的员工
            DepartmentStaff::whereIn('staff_id', $staff_id_list)->where('department_id', $department_id)->delete();
            $isset_staff_id_list = DepartmentStaff::whereIn('staff_id', $staff_id_list)->groupBy('staff_id')->pluck('staff_id')->toArray();
            if ($delete_staff_id = array_diff($staff_id_list, $isset_staff_id_list)) { //禁用无部门归属的员工
                Staff::whereIn('id', $delete_staff_id)->update(['disabled' => 1]);
            }
        }
    }

    /*
     * 获取微信员工列表
     */
    public static function getWechatStaffList($department_id, $staff_id, $wechat_user_id)
    {
        $class = new QyWechatRequestService();
        if ($department_id) { //更新全部员工
            $department = Department::business()->find($department_id);
            if (!$department->wechat_department_id) {
                return self::returnArr(0, '该部门未与企业微信关联');
            }
            $res = $class->request('getStaffDetailList', ['department_id' => $department->wechat_department_id]);
            if (!$res['result']) {
                return self::returnArr(0, $res['msg']);
            }
            $staff_list = $res['data']['userlist'];
        } elseif ($staff_id) {  //更新指定id员工
            if (!$staff = Staff::business()->find($staff_id)) {
                return self::returnArr(0, '员工不存在');
            }
            $res = $class->request('getStaffDetail', ['userid' => $staff->user_id]);
            if (!$res['result']) {
                return self::returnArr(0, $res['msg']);
            }
            $staff_list = [$res['data']];
        } elseif ($wechat_user_id) {  //更新指定userid员工
            $res = $class->request('getStaffDetail', ['userid' => $wechat_user_id]);
            if (!$res['result']) {
                return self::returnArr(0, $res['msg']);
            }
            $staff_list = [$res['data']];
        } else {
            return self::returnArr(0, '请选择要同步的部门或员工');
        }

        return self::returnArr(1, '成功', $staff_list);
    }


    /*
     * 获取可能跟员工相关联的商城会员
     */
    public static function getConnectionMember($wechat_staff, $check_isset = 1)
    {
        $uid = 0;

        if (app('plugins')->isEnabled('work-wechat')) { //判断旧雇员表是否有相关数据
            $old_staff = Employee::uniacid()->with('hasOneMember')
                ->where('crop_id', SettingService::getBusinessId())
                ->where('userid', $wechat_staff['user_id'])
                ->first();
            if ($old_staff->hasOneMember->uid) {
                $uid = $old_staff->hasOneMember->uid;
            }
        }

        if (!$uid && $wechat_staff['mobile']) { //判断商城会员是否有手机号关联会员
            if ($member = Member::uniacid()->where('mobile', $wechat_staff['mobile'])->first()) $uid = $member->uid;
        }


        if ($uid && $check_isset) { //判断是否有重复绑定商城会员的员工
            if (Staff::business()->where('uid', $uid)->first()) $uid = 0;
        }

        return $uid;
    }


    /*
     * 组装企业微信员工数据
     */
    public static function formWechatStaff($business_id, $v)
    {
        ;
        //企业微信隐私数据限制
        if (!isset($v['mobile']) && ($ticket = StaffTicket::uniacid()->business($business_id)->userId($v['userid'])->orderBy('id', 'DESC')->first())) {
            $res = (new QyWechatRequestService($business_id))->request('getUserDetail', ['user_ticket' => $ticket->ticket]);
            if ($res['result']) {
                $columns = ['gender', 'avatar', 'qr_code', 'mobile', 'email', 'address'];
                foreach ($columns as $column) {
                    if ($res['data'][$column]) {
                        $v[$column] = $res['data'][$column];
                    }
                }
            }
        }

        return [
            'business_id' => $business_id,
            'user_id' => $v['userid'],
            'name' => $v['name'],
            'mobile' => $v['mobile'],
            'position' => $v['position'] ?: '',
            'gender' => $v['gender'] ?: 0,
            'telephone' => $v['telephone'] ?: '',
            'email' => $v['email'] ?: '',
            'avatar' => $v['avatar'] ?: '',
            'alias' => $v['alias'] ?: '',
            'status' => $v['status'],
            'qr_code' => $v['qr_code'] ?: '',
            'address' => $v['address'] ?: '',
            'open_userid' => $business_id . '_' . $v['userid'],
            'main_department' => $v['main_department'] ?: '',
        ];

    }


    /*
     * 为员工列表添加权限数据
     */
    public static function addStaffPremission(&$data)
    {
        $right_arr = BusinessService::checkBusinessRight();
        $function_arr = ['updateStaff', 'deleteStaff'];
        if ($right_arr['identity'] < 2) {
            $department_staff = DepartmentStaff::whereIn('staff_id', array_column($data, 'staff_id'))->get();
        }
//        $array = ['']
        foreach ($data as &$v) {
            $v['premission']['setAuth'] = $right_arr['identity'] < 2 ? 0 : 1;
            foreach ($function_arr as $vv) {
                if ($right_arr['identity'] > 1) {
                    $v['premission'][$vv] = 1;
                } elseif (!$right_arr['route_department_id'][$vv]) {
                    $v['premission'][$vv] = 0;
                } elseif ($department_staff->where('staff_id', $v['staff_id'])->whereIn('department_id', $right_arr['route_department_id'][$vv])->isNotEmpty()) {
                    $v['premission'][$vv] = 1;
                } else {
                    $v['premission'][$vv] = 0;
                }
            }
        }
    }


    /*
     * 获取员工列表
     */
    public static function getStaffList($department_id, $data = [])
    {
        $where = [];
        if (is_array($department_id)) {
            $where[] = [function ($query) use ($department_id) {
                $query->whereIn('department_id', $department_id);
            }];
        } else {
            $where[] = ['department_id', $department_id];
        }

        $staff_where = [];
        if ($kwd = $data['kwd']) {
            $staff_where[] = [function ($q_kwd) use ($kwd) {
                $q_kwd->where('name', 'like', "%{$kwd}%")->orWhere('mobile', 'like', "%{$kwd}%");
            }];
        }

        if ($id = $data['id']) {
            $staff_where[] = ['id', $id];
        }

        if ($data['status'] || $data['status'] === 0 || $data['status'] === '0') {
            $staff_where[] = ['status', $data['status']];
        }

        if ($data['disabled'] === 0 || $data['disabled'] === '0') {
            $staff_where[] = ['disabled', 0];
        } elseif ($data['disabled'] == 1) {
            $staff_where[] = ['disabled', 1];
        }


        if ($staff_where) {
            $staff_id_list = Staff::business()->where($staff_where)->pluck('id')->toArray() ?: [-1];
            $where[] = [function ($query) use ($staff_id_list) {
                $query->whereIn('staff_id', $staff_id_list);
            }];
        }

        $list = DepartmentStaff::business()->where($where)->with(['hasOneStaff' => function ($query) use ($data) {
            $query->with(['hasOneMember' => function ($query1) {
                $query1->select('uid', 'avatar', 'nickname', 'realname');
            }]);
        }])->groupBy('staff_id')->select('id', 'staff_id', 'department_id', 'is_leader');

        return $list;

    }

    /*
     * 将中文转换成拼音字符串
     */
    public static function overTrue($string, $length = 0)
    {
        $arr = (new \Overtrue\Pinyin\Pinyin())->convert($string);
        $return_string = '';
        foreach ($arr as $v) {
            $return_string .= strtoupper($v);
        }

        return $length ? substr($return_string, 0, $length) : $return_string;
    }

    public static function returnArr($result, $msg = '', $data = [])
    {
        return ['result' => $result, 'msg' => $msg, 'data' => $data];
    }

}
