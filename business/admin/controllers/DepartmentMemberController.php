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

use app\backend\modules\member\models\Member;
use business\common\models\Department;
use business\common\models\DepartmentStaff;
use business\common\models\Staff;
use Exception;
use Illuminate\Support\Facades\DB;
use Yunshop\WorkWechat\common\models\Employee;
use business\common\services\SettingService;

class DepartmentMemberController extends \business\common\controllers\components\BaseController
{
    public function getDepartmentMember(Department $department)
    {
        $data = $department->getDepartmentMember();
        $res = $this->recursion($data);
        return $this->successJson('ok',$res);
    }

    private function recursion($data, $parent_id = 0)
    {
        $res = [];
        foreach ($data as $item) {
            if ($item['parent_id'] == $parent_id) {
                $item['children'] = $this->recursion($data, $item['id']);
                if (empty($item['children'])) {
                    unset($item['children']);
                }
                array_push($res, $item);
            }
        }
        return $res;
    }

    //弃用
    public function t()
    {
        $data = Department::uniacid()->where('business_id', SettingService::getBusinessId())
            ->with(['hasMayDepartmentStaff.hasOneStaff'])->get()->groupBy('level')->toArray();

        $data = $this->tt($data[1], 1, $data);
        return $this->successJson('ok', $data);
    }

    // 临时写成以数组方式，集合有点bug，只有一个部门返回给前端不是数组而是对象
    public function tt(array $list, $level = 1, $lists)
    {
        foreach ($list as &$item) {
            $item['type'] = "department";

            // 获取该部门的员工
            $staff_ids = DepartmentStaff::uniacid()
                ->where('business_id', SettingService::getBusinessId())
                ->where('department_id', $item['id'])->pluck('staff_id');
            $staff_data = Staff::uniacid()->whereIn('id', $staff_ids)->get()->toArray();

            $staff = [];
            foreach ($staff_data as $key => $items) {
                $staff[$key] = $items;
                $staff[$key]['type'] = "staff";
            }
            if ($staff) {
                $item['staff'] = $staff;//array_values(array_unique($staff));
            } else {
                $item['staff'] = [];
            }

            // 获取子部门
            if ($c = $lists[$level + 1]) {
                $c = collect($c);
                $children = $c->where('parent_id', $item['id'])->all();
                if (!$children) {
                    $item['children'] = [];
                } else {
                    $children = $this->recursion($children, $level + 1, $lists);
                    $item['children'] = $children[0] ? $children : array_merge([],$children);
                }
            } else {
                $item['children'] = [];
            }
        }

        return $list;
    }


}