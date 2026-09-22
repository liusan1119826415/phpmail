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


use business\admin\menu\BusinessMenu;
use business\common\models\Department;
use business\common\models\Premission;
use business\common\models\Staff;
use Illuminate\Support\Facades\DB;
use Exception;

class AuthService
{

    /*
     * 设置权限
     */
    public static function setAuth($auth, $department_id = 0, $is_leader = 0, $staff_id = 0, $right_type = 0)
    {
        $where = [
            'business_id' => SettingService::getBusinessId(),
            'uniacid' => \YunShop::app()->uniacid,
        ];
        if ($department_id) {
            if (!Department::business()->find($department_id)) {
                return self::returnArr(0, '部门不存在');
            }
            $where['department_id'] = $department_id;
            $where['type'] = $is_leader ? 2 : 1;
        } elseif ($staff_id) {
            if (!$staff = Staff::business()->find($staff_id)) {
                return self::returnArr(0, '员工不存在');
            }
            $where['staff_id'] = $staff_id;
            $where['type'] = 3;
        } else {
            return self::returnArr(0, '权限类型异常');
        }

        DB::beginTransaction();
        $insert_data = [];
        $time = time();
        try {
            foreach ($auth as $k => $v) {
                foreach ($v as $kk => $vv) {
                    $insert_data[] = array_merge($where, [
                        'module' => $k,
                        'route' => $vv,
                        'created_at' => $time,
                        'updated_at' => $time
                    ]);
                }
                Premission::where($where)->delete();
            }

            if ($insert_data) {
                Premission::insert($insert_data);
            }

            if (isset($staff)) {
                $staff->right_type = in_array($right_type, [0, 1]) ? $right_type : 0;
                $staff->save();
            }

        } catch (Exception $e) {
            DB::rollBack();
            return self::returnArr(0, $e->getMessage());
        }

        DB::commit();
        return self::returnArr(1, '成功');

    }


    /*
     * 获取权限列表
     */
    public static function getAuthList($department_id = 0, $is_leader = 0, $staff_id = 0)
    {
        $menu = (new BusinessMenu())->getMenu();
        $menu = $menu['all_route'];

        if ($department_id) {
            if (!Department::business()->find($department_id)) {
                return self::returnArr(0, '部门不存在');
            }
            $where[] = ['department_id', $department_id];
            $where[] = ['type', $is_leader ? 2 : 1];
        } elseif ($staff_id) {
            if (!Staff::business()->find($staff_id)) {
                return self::returnArr(0, '员工不存在');
            }
            $where[] = ['staff_id', $staff_id];
            $where[] = ['type', 3];
        } else {
            return self::returnArr(0, '权限类型异常');
        }

        $right_arr = Premission::business()->where($where)->get();

        $return_data = [];

        foreach ($menu as $k => $v) {
            $return_data[$k] = self::foreachAuthList($v, $right_arr, $k);
        }

        return self::returnArr(1, '成功', $return_data);

    }

    /*
     * 循环组装权限列表数据
     */
    private static function foreachAuthList(&$menu, &$right_arr, $module)
    {
        foreach ($menu as $k => $v) {

            if (!$v['premit']) {
                unset($menu[$k]);
                continue;
            }

            $menu[$k] = [
                'name' => $v['page_name'],
                'route' => $v['route'],
                'can' => $right_arr->where('route', $v['route'])->where('module', $module)->isNotEmpty() ? 1 : 0,
                'child' => [],
            ];

            if ($v['child']) {
                $menu[$k]['can'] = 0;
                $menu[$k]['child'] = self::foreachAuthList($v['child'], $right_arr, $module);
            }

        }

        return array_values($menu);
    }


    public static function returnArr($result, $msg = '', $data = [])
    {
        return ['result' => $result, 'msg' => $msg, 'data' => $data];
    }

}