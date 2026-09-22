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

use business\common\models\Department;
use business\common\models\Premission;
use business\common\models\Staff;

class DepartmentPremissionService
{
    public $right_arr;
    public $key;
    public $business_id;
    public $uid;
    public $return_data;
    public $right_type;
    public $member_department_id;
    public $all_department_id;
    public $business_department_list;
    public $identity;

    public function __construct($business_id = 0, $uid = 0)
    {
        $this->business_id = $business_id ?: SettingService::getBusinessId();
        $this->uid = $uid ?: \YunShop::app()->getMemberId();
        $this->right_arr = $this->getRightArr($business_id, $uid);
        $this->return_data = [];
        $this->all_department_id = Department::business($this->business_id)->pluck('id')->toArray();
        $this->key = [
            'createDepartment', 'updateDepartment', 'deleteDepartment',
            'getStaffList', 'createStaff', 'updateStaff', 'deleteStaff'
        ];
    }

    /*
   * 筛选指定接口的权限部门id
   */
    public function getAllPremissionDepartmentId($all_right = 0)
    {
        foreach ($this->key as $v) {
            if ($all_right) {
                $this->return_data[$v] = $this->all_department_id;
            } else {
                $this->return_data[$v] = $this->$v();
            }
        }
        return $this->return_data;
    }


    /*
     * 获取部门员工列表
     */
    public function getBusinessDepartmentList()
    {
        if (!$this->business_department_list) {
            $this->business_department_list = Department::where('business_id', $this->business_id)->get();
        }
        return $this->business_department_list;
    }

    private function updateStaff()
    {
        if ($this->right_type == 1) { //如果是独立权限
            $department_id_arr = $this->right_arr->where('route', 'updateStaff')->isEmpty() ? [] : $this->member_department_id;
        } else {
            $department_id_arr = $this->getCommonManageDepartment('updateStaff');
        }
        return $department_id_arr ? $department_id_arr : [];
    }

    private function deleteStaff()
    {
        if ($this->right_type == 1) { //如果是独立权限
            $department_id_arr = $this->right_arr->where('route', 'deleteStaff')->isEmpty() ? [] : $this->member_department_id;
        } else {
            $department_id_arr = $this->getCommonManageDepartment('deleteStaff');
        }
        return $department_id_arr ? $department_id_arr : [];
    }


    private function createStaff()
    {

        if ($this->right_type == 1) { //如果是独立权限
            $department_id_arr = $this->right_arr->where('route', 'createStaff')->isEmpty() ? [] : $this->member_department_id;
        } else {
            $department_id_arr = $this->getCommonManageDepartment('createStaff');
        }

        return $department_id_arr ? $department_id_arr : [];
    }


    private function getStaffList()
    {
        if ($this->right_type == 1) { //如果是独立权限
            $department_id_arr = $this->right_arr->where('route', 'getStaffList')->isEmpty() ? [] : $this->member_department_id;
        } else {
            $department_id_arr = $this->getCommonManageDepartment('getStaffList');
        }
        return $department_id_arr ? array_merge($department_id_arr, Department::getAllDepartmentSubId($department_id_arr, $this->getBusinessDepartmentList(), $this->business_id)) : [];
    }

    private function deleteDepartment()
    {

        if ($this->right_arr->where('route', 'deleteAllDepartment')->isNotEmpty()) {
            return $this->all_department_id;
        }

        if ($this->right_type == 1) { //如果是独立权限
            $department_id_arr = $this->right_arr->where('route', 'deleteSubDepartment')->isEmpty() ? [] : $this->member_department_id;
        } else {
            $department_id_arr = $this->getCommonManageDepartment('deleteSubDepartment');
        }

        return $department_id_arr ? array_merge($department_id_arr, Department::getAllDepartmentSubId($department_id_arr, $this->getBusinessDepartmentList(), $this->business_id)) : [];
    }

    private function updateDepartment()
    {

        if ($this->right_arr->where('route', 'updateAllDepartment')->isNotEmpty()) {
            return $this->all_department_id;
        }

        if ($this->right_type == 1) { //如果是独立权限
            $department_id_arr = $this->right_arr->where('route', 'updateSubDepartment')->isEmpty() ? [] : $this->member_department_id;
        } else {
            $department_id_arr = $this->getCommonManageDepartment('updateSubDepartment');
        }

        return $department_id_arr ? array_merge($department_id_arr, Department::getAllDepartmentSubId($department_id_arr, $this->getBusinessDepartmentList(), $this->business_id)) : [];
    }


    private function createDepartment()
    {

        if ($this->right_arr->where('route', 'createAllDepartment')->isNotEmpty()) {
            return $this->all_department_id;
        }

        if ($this->right_type == 1) { //如果是独立权限
            $department_id_arr = $this->right_arr->where('route', 'createSubDepartment')->isEmpty() ? [] : $this->member_department_id;
        } else {
            $department_id_arr = $this->getCommonManageDepartment('createSubDepartment');
        }

        return $department_id_arr ? array_merge($department_id_arr, Department::getAllDepartmentSubId($department_id_arr, $this->getBusinessDepartmentList(), $this->business_id)) : [];
    }


    private function getCommonManageDepartment($key)
    {
        $res = $this->right_arr->where('route', $key)->pluck('department_id')->toArray();
        return $res ? array_values(array_filter(array_unique($res))) : [];
    }


    /*
     * 获取当前用户已经被勾选允许的权限路由和页面
     */
    public function getRightArr($business_id = 0, $uid = 0)
    {

        if ($this->right_arr) {
            return $this->right_arr;
        }

        $business_id = $business_id ?: SettingService::getBusinessId();
        $uid = $uid ?: \YunShop::app()->getMemberId();

        $staff = Staff::business($business_id)->with(['hasManyDepartmentStaff' => function ($query) {
            $query->with('hasOneDepartment');
        }])->where('uid', $uid)->first();

        if (!$staff) {
            return collect((Object)[]);
        }

        $member_department_id = []; //全部关联部门ID
        $common_department_id = []; //是成员的部门ID
        $leader_department_id = [];//全部关联部门ID

        if ($staff->hasManyDepartmentStaff->isNotEmpty()) {
            $staff->hasManyDepartmentStaff->each(function ($v) use (&$common_department_id, &$leader_department_id) {
                if (!$v->hasOneDepartment->id) return;
                $v->is_leader ? $leader_department_id[] = $v->department_id : $common_department_id[] = $v->department_id;
            });
            $member_department_id = array_merge($common_department_id, $leader_department_id);
        }

        $this->member_department_id = $member_department_id;
        $this->right_type = $staff->right_type;

        if ($staff->right_type == 1) { //如果使用成员独立权限
            $right_arr = Premission::business($business_id)->where('staff_id', $staff->id)->where('type', 3)->get();
        } else { //如果使用部门权限
            if ($staff->hasManyDepartmentStaff->isNotEmpty()) {

                if (!$member_department_id) {
                    return collect((Object)[]);
                }

                $where = [
                    ['uniacid', \YunShop::app()->uniacid],
                    ['business_id', $business_id],
                ];

                $where[] = [function ($query) use ($common_department_id, $leader_department_id) {

                    if ($common_department_id) {
                        $common_where = function ($query1) use ($common_department_id) {
                            $query1->whereIn('department_id', $common_department_id)->where('type', 1); //获取相关部门成员权限
                        };
                    }

                    if ($leader_department_id) {
                        $leader_where = function ($query2) use ($leader_department_id) {
                            $query2->whereIn('department_id', $leader_department_id)->where('type', 2); //获取相关部门领导权限
                        };
                    }

                    if ($common_department_id && $leader_department_id) {
                        $query->where($common_where)->orWhere($leader_where);
                    } elseif ($common_department_id) {
                        $query->where($common_where);
                    } elseif ($leader_department_id) {
                        $query->where($leader_where);
                    }
                }];

                $right_arr = Premission::where($where)->get(); //获取成员成员&领导权限

            }
        }

        if (!$right_arr){
            $right_arr = collect((Object)[]);
        }

        $this->right_arr = $right_arr;
        return $this->right_arr;
    }


    public static function returnArr($result, $msg = '', $data = [])
    {
        return ['result' => $result, 'msg' => $msg, 'data' => $data];
    }

}