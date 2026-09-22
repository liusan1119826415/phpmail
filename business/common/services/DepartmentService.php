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

use app\common\exceptions\AppException;
use business\common\models\Department;
use business\common\models\DepartmentStaff;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Exception;

class DepartmentService
{


    /*
     * 根据方法获取可操作的部门
     */
    public static function checkDepartmentIdByMethod($method, $department_id)
    {

        $right = BusinessService::checkBusinessRight();
        if ($right['identity'] > 1) {
            return true;
        }

        if (empty($right['route_department_id'][$method])) {
            return false;
        }

        if (in_array($department_id, $right['route_department_id'][$method])) {
            return true;
        }

        return false;

    }

    /*
     * 删除部门
     */
    public static function deleteDepartment($department_id, $is_callback = 0)
    {

        DB::beginTransaction();

        try {

            if (!$department = Department::business()->find($department_id)) {
                throw new AppException('部门不存在');
            }

            if ($department->level == 1) {
                throw new AppException('一级部门不允许删除');
            }

            if ($delete_sub_department_id = Department::getAllDepartmentSubId([$department->id])) {
                throw new AppException('请先删除下级部门');
            }

            if ($is_callback){

                if (DepartmentStaff::where('department_id', $department->id)->delete()) {
                    throw new AppException('请先移除该部门的员工');
                }

                if (!$department->delete()) {
                    throw new AppException('部门删除失败');
                }
            }else{
                if (DepartmentStaff::where('department_id', $department->id)->first()) {
                    throw new AppException('请先移除该部门的员工');
                }

                if (!$department->delete()) {
                    throw new AppException('部门删除失败');
                }

                if ($department->wechat_department_id && SettingService::EnabledQyWx()) {
                    $res = (new QyWechatRequestService())->request('deleteDepartment', ['id' => $department->wechat_department_id]);
                    if (!$res['result']) {
                        throw new AppException($res['msg']);
                    }
                }
            }

        } catch (Exception $e) {
            DB::rollBack();
            return self::returnArr(0, $e->getMessage());
        }

        DB::commit();

        BusinessService::flush(SettingService::getBusinessId()); //清除企业缓存

        return self::returnArr(1, '删除部门成功');

    }

    /*
     * 创建/编辑部门
     */
    public static function changeDepartment($name, $parent_id = 0, $id = 0, $order = 0, $enable = 1)
    {

        DB::beginTransaction();

        try {

            $business_id = SettingService::getBusinessId();

            if ($parent_id) {
                if (!$parent_department = Department::business()->find($parent_id)) {
                    throw new AppException('上级部门不存在');
                }

                if (SettingService::EnabledQyWx() && $parent_department->wechat_department_id == 0) {
                    throw new AppException('上级部门未与企业微信关联');
                }
            } else {
                if (!$id && Department::business()->where('level', 1)->first()) {
                    throw new AppException('一级部门只允许存在一个');
                }
            }


            $update_data = [
                'name' => $name,
                'parent_id' => $parent_id,
                'level' => $parent_department ? $parent_department->level + 1 : 1,
            ];

            if (!$id) {

                if (Department::where($update_data)->first()) {
                    throw new AppException('同一部门下不能存在名称相同的部门');
                }

                $update_data['order'] = intval($order) ?: 10000;
                if (!$department = Department::create(array_merge($update_data, [
                    'business_id' => $business_id,
                    'uniacid' => \YunShop::app()->uniacid,
                    'wechat_department_id' => 0
                ]))) {
                    throw new AppException('创建部门失败');
                }
            } else {
                if (!$department = Department::business()->find($id)) {
                    throw new AppException('部门不存在');
                }
                if (intval($order)) $update_data['order'] = intval($order);
                $department->fill($update_data);
                $department->save();
            }

            if (SettingService::EnabledQyWx() && $enable) {  //如果开启了企业微信设置，进行推送
                $res = self::pushDepartment($department);
                if (!$res['result']) {
                    throw new AppException($res['msg']);
                }
            }

        } catch (Exception $e) {
            DB::rollBack();
            return self::returnArr(0, $e->getMessage());
        }

        DB::commit();
        BusinessService::flush(SettingService::getBusinessId()); //清除企业缓存

        return self::returnArr(1, '成功');

    }

    /*
     * 推送部门到企业微信
     */
    public static function pushDepartment(Department $department)
    {
        try {

            if ($department->level == 1 && !$department->wechat_department_id) {
                $first_department = (new QyWechatRequestService())->request('getDepartmentList');
                foreach ($first_department['data']['department'] as $v) {
                    if ($v['parentid'] == 0 && $v['name'] == $department->name) {
                        $department->wechat_department_id = $v['id'];
                        $department->save();
                        break;
                    }
                }

                if (!$department->wechat_department_id) {
                    throw new AppException('一级部门未与企业微信关联');
                }
            }

            if ($department->level != 1 && !$department->hasOneParentDepartment->wechat_department_id) {
                throw new AppException($department->name . '的上级部门未与企业微信关联');
            }

            $request_data = [
                'id' => $department->wechat_department_id ?: 0,
                'name' => $department->name,
                'parent_id' => $department->hasOneParentDepartment->wechat_department_id ?: 0,
                'order' => $department->order,
            ];

            //判断是创建同步 还是 编辑同步
            $function = $request_data['id'] ? 'updateDepartment' : 'createDepartment';
            $res = (new QyWechatRequestService())->request($function, $request_data);

            if (!$res['result']) {
                throw new AppException($res['msg']);
            }

            if ($function == 'createDepartment') {
                $department->wechat_department_id = $res['data']['id'];
                $department->save();
            }

        } catch (Exception $e) {
            return self::returnArr(0, $e->getMessage());
        }

        return self::returnArr(1, '推送部门成功');

    }


    /*
     * 给部门添加权限数据
     */
    public static function addDepartmentPremission($data, &$right = [], $parent_name = '', &$count = [])
    {
        if (!$count) {
            $count = DepartmentStaff::business()->select(DB::raw('COUNT(distinct staff_id) as count'), 'department_id')->groupBy('department_id')->get()->toArray();
            $count = array_column($count, 'count', 'department_id');
        }
        $function_arr = ['createDepartment', 'updateDepartment', 'deleteDepartment', 'getStaffList', 'createStaff', 'updateStaff'];
        if (!$right) {
            $right = BusinessService::checkBusinessRight();
        }
        foreach ($data as &$v) {
            $v['staff_count'] = $count[$v['id']] ?: 0;
            $v['parent_name'] = $parent_name;
            $v['premission']['setDepartmentLeader'] = $right['identity'] > 1 ? 1 : 0;
            foreach ($function_arr as $vv) {
                if ($right['identity'] > 1) {
                    $v['premission'][$vv] = 1;
                } else {
                    $v['premission'][$vv] = in_array($v['id'], $right['route_department_id'][$vv]) ? 1 : 0;
                }
            }
            $v['create_staff_disabled'] = $v['premission']['createStaff'] ? false : true;
            $v['update_staff_disabled'] = $v['premission']['updateStaff'] ? false : true;
            $v['create_department_disabled'] = $v['premission']['createDepartment'] ? false : true;
            if ($v['has_one_sub_department']) {
                $v['has_one_sub_department'] = self::addDepartmentPremission($v['has_one_sub_department'], $right, $v['name'], $count);
            }
        }

        return $data;
    }


    /*
     * 获取部门列表
     */
    public static function getDepartmentList($parent_id = 0)
    {
        if (!$business_id = SettingService::getBusinessId()) {
            return self::returnArr(0, '请先选择要管理的企业');
        }

        $where = [['business_id', $business_id],];

        if ($parent_id) {
            if (!$parent_department = Department::uniacid()->where($where)->find($parent_id)) {
                return self::returnArr(0, '部门不存在');
            }
            $where[] = ['level', '>', $parent_department->level];
        }

        $department_list = Department::uniacid()->where($where)->orderBy('order', 'DESC', 'id', 'ASC')
            ->select('id', 'business_id', 'name', 'level', 'parent_id', 'wechat_department_id', 'order')->get();

        $return_data = [];
        if ($parent_department) {
            $return_data[] = $parent_department->toArray();
        } elseif ($department_list->isNotEmpty()) {
            $return_data = $department_list->where('level', 1)->values()->toArray();
        }

        $department_list = $department_list->toArray();
        foreach ($return_data as $k => $v) {
            $return_data[$k]['has_one_sub_department'] = self::foreachDepartment($department_list, $v['id']);
        }

        return self::returnArr(1, '成功', $return_data);
    }


    /*
     * 递归获取部门列表下级（不使用集合，很慢）
     */
    public static function foreachDepartment(&$department_list, $id)
    {
        $res = [];
        foreach ($department_list as $k => $v) {
            if ($v['parent_id'] == $id) {
                unset($department_list[$k]);
                $v['has_one_sub_department'] = static::foreachDepartment($department_list, $v['id']);
                if (empty($v['has_one_sub_department'])) {
                    unset($v['has_one_sub_department']);
                }
                $res[] = $v;
            }
        }

        return $res;
    }

    /*
     * 获取所有下级部门id(包括当前)
     */
    public static function getDepartmentChildIds($department_ids,$business_id = 0)
    {
        $department_list = Department::business($business_id)->select('id', 'name', 'level', 'parent_id')->get();
        $check_department_list = $department_list->whereIn('id', $department_ids);
        $group_department_list = $department_list->groupBy('level');

        static::$department_ids = array();
        unset($department_list);
        foreach ($check_department_list as $department) {
            static::recursionDown($department, $group_department_list);
        }
        $ids = array_values(array_filter(array_unique(static::$department_ids)));
        static::$department_ids = array();

        return $ids;
    }

    static $department_ids = array();

    public static function recursionDown(Department $department, Collection $groupList)
    {
        if (in_array($department->id, static::$department_ids)) {
            return static::$department_ids;
        }
        static::$department_ids[] = $department->id;
        if (!$group = $groupList[$department->level + 1]) {
            return static::$department_ids;
        }

        foreach ($group as $key => $item) {
            if ($item->id == $department->parent_id) {
                unset($group[$key]);
                static::recursionDown($item, $groupList);
            }
        }

        return static::$department_ids;
    }


    /*
     * 从企业微信同步部门列表
     */
    public static function refreshDepartment($business_id = 0)
    {

        try {
            if (!$business_id = $business_id ?: SettingService::getBusinessId()) {
                throw new AppException('请先选择要管理的企业');
            }
            $class = new QyWechatRequestService();
            $res = $class->request('getDepartmentList');
            if (!$res['result']) {
                throw new AppException($res['msg']);
            }

            /*删除部门*/
            $all_department_id_list = array_column($res['data']['department'], 'id');
            Department::business($business_id)->where('wechat_department_id', '<>', 0)->whereNotIn('wechat_department_id', $all_department_id_list)->delete();
            /*删除部门*/

            if ($department = $res['data']['department']) {
                $department = self::formWechatDepartment($department);
                foreach ($department as $k => $v) {
                    foreach ($v as $kk => $vv) {
                        $is_create = false;
                        $this_isset_department = Department::business($business_id)->with('hasOneParentDepartment')
                            ->where('wechat_department_id', $vv['id'])->first();

                        if ($this_isset_department) { //判断是否存在已绑定了微信部门并且上级id关联正确的数据
                            if (($this_isset_department->hasOneParentDepartment->wechat_department_id != $vv['parentid'] && $vv['parentid'] != 0)) {
                                if (!$true_parent_department = Department::business($business_id)->where('wechat_department_id', $vv['parentid'])->first()) {
                                    $this_isset_department->wechat_department_id = 0;
                                    $this_isset_department->save();
                                    $is_create = true;
                                    unset($this_isset_department);
                                } else {
                                    $old_level = $this_isset_department->level;
                                    $new_level = $true_parent_department->level + 1;
                                    $change_level = $new_level - $old_level;
                                    $this_isset_department->parent_id = $true_parent_department->id;
                                    $this_isset_department->save();
                                    if ($change_level != 0) {
                                        $sub_level_id = Department::getAllDepartmentSubId([$this_isset_department->id]);
                                        Department::whereIn('id', array_merge([$this_isset_department->id], $sub_level_id))->update([
                                            'level' => DB::raw("level + {$change_level}")
                                        ]);
                                    }
                                }
                            } elseif ($vv['parentid'] == 0 && $this_isset_department->hasOneParentDepartment) {
                                $this_isset_department->wechat_department_id = 0;
                                $this_isset_department->save();
                                $is_create = true;
                                unset($this_isset_department);
                            }
                        } else {
                            $is_create = true;
                        }


                        if (!$is_create) { //如果存在已绑定了微信部门并且上级id关联正确的数据
                            $this_isset_department->name = $vv['name'];
                            $this_isset_department->order = $vv['order'] ?: 1;
                            $this_isset_department->save();
                            continue;
                        }


                        $where = [
                            ['name', 'like', $vv['name']],
                            ['level', $vv['level']]
                        ];
                        $this_isset_departments = Department::uniacid()->with('hasOneParentDepartment')
                            ->where('business_id', SettingService::getBusinessId())
                            ->where($where)->get();

                        if ($this_isset_departments->isNotEmpty()) { //判断是否存在层级相同、名称相同 并且 没有关联微信部门 并且 微信上级id正确的部门
                            $is_break = false;
                            $this_isset_departments->each(function ($value) use (&$is_break, $vv) {
                                if (!$is_break && ($vv['parentid'] == $value->hasOneParentDepartment->wechat_department_id || $vv['parentid'] == 0)) {
                                    $value->wechat_department_id = $vv['id'];
                                    $value->order = $vv['order'];
                                    $value->save();
                                    $is_break = true;
                                }
                            });
                            if ($is_break) {
                                continue;
                            }
                        }

                        if ($vv['parentid']) {
                            $parent = Department::business($business_id)->where('wechat_department_id', $vv['parentid'])->first();
                            $parent_id = $parent->id ?: 0;
                        } else {
                            $parent_id = 0;
                            $department = Department::business($business_id)->where('level', 1)->first();
                            if ($department) {
                                $department->name = $vv['name'] ?: '';
                                $department->en_name = $vv['en_name'] ?: '';
                                $department->order = $vv['order'];
                                $department->wechat_department_id = $vv['id'];
                                $department->save();
                                continue;
//                                throw new AppException('只能存在1个一级部门,请将一级部门名称修改为与企业微信一级部门(' . $vv['name'] . ')一致再尝试同步');
                            }
                        }
                        Department::create([
                            'uniacid' => \YunShop::app()->uniacid,
                            'business_id' => $business_id,
                            'name' => $vv['name'] ?: '',
                            'en_name' => $vv['en_name'] ?: '',
                            'level' => $vv['level'],
                            'parent_id' => $parent_id,
                            'order' => $vv['order'],
                            'wechat_department_id' => $vv['id']
                        ]);
                    }
                }
            }

            BusinessService::flush(SettingService::getBusinessId()); //清除企业缓存
        } catch (Exception $e) {
            return self::returnArr(0, $e->getMessage());
        }
        return self::returnArr(1, '成功');

    }


    /*
     * 组装企业微信接口返回的部门列表
     */
    public static function formWechatDepartment($department)
    {
        $return_data = [];
        $all_data = [];
        do {
            foreach ($department as $k => $v) {
                if ($v['parentid'] == 0) {
                    $v['level'] = 1;
                    $return_data[$v['level']][] = $all_data[$v['id']] = $v;
                    unset($department[$k]);
                } else {
                    if (isset($all_data[$v['parentid']])) {
                        $v['level'] = $all_data[$v['parentid']]['level'] + 1;
                        $return_data[$v['level']][] = $all_data[$v['id']] = $v;
                        unset($department[$k]);
                    }
                }
            }
        } while (count($department) != 0);

        return $return_data;
    }


    public static function returnArr($result, $msg = '', $data = [])
    {
        return ['result' => $result, 'msg' => $msg, 'data' => $data];
    }

    // 递归获取当前部门所有下级id
    public static function getDepartmentIds($department_id = 0, $is_identity = false)
    {
        static $ids = array();
        $model = Department::business()->select('id');
        if (!$department_id) {
            $model = $model->where(['level' => 1, 'parent_id' => 0])->get();
        } else {
            $model = $model->where('parent_id', $department_id)->get();
        }

        if ($model->isNotEmpty()) {
//            $ids = array_unique(array_merge($ids,$model->pluck('id')->toArray()));
            foreach ($model as $v) {
                if ($is_identity) {
                    if (!DepartmentService::checkDepartmentIdByMethod('getStaffList', $v->id)) {
                        continue;
                    }
                }
                $ids[] = $v->id;
                self::getDepartmentIds($v->id, $is_identity);
            }
        }

        return array_unique($ids);
    }

}
