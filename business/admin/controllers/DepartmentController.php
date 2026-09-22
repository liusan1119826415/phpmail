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

use business\common\models\Department;
use business\common\models\DepartmentStaff;
use business\common\services\DepartmentService;
use business\common\services\SettingService;
use business\common\controllers\components\BaseController;
use Exception;

class DepartmentController extends BaseController
{

    /*
     * 从企业微信同步部门列表
     */
    public function refreshDepartmentList()
    {

        if (!SettingService::EnabledQyWx()) {
            return $this->errorJson('未开启企业微信', ['is_set' => 1]);
        }

        $res = DepartmentService::refreshDepartment();
        if (!$res['result']) {
            return $this->errorJson($res['msg']);
        }
        return $this->successJson('成功');
    }

    /*
     * 获取部门列表
     */
    public function getDepatmemtList()
    {
        $res = DepartmentService::getDepartmentList();

        if (!$res['result']) {
            return $this->errorJson($res['msg']);
        }
        $res['data'] = DepartmentService::addDepartmentPremission($res['data']);
        return $this->successJson('成功', ['list' => $res['data']]);
    }


    /*
     * 添加部门
     */
    public function createDepartment()
    {

        if (!DepartmentService::checkDepartmentIdByMethod('createDepartment', \request()->parent_id)) {
            return $this->errorJson('无权操作');
        }

        $order = intval(\request()->order) ?: null;
        $res = DepartmentService::changeDepartment(\request()->name, \request()->parent_id??0, 0, $order);
        if (!$res['result']) {
            return $this->errorJson($res['msg']);
        }

        return $this->successJson('创建成功');
    }


    /*
     * 编辑部门
     */
    public function updateDepartment()
    {
        if (!DepartmentService::checkDepartmentIdByMethod('updateDepartment', \request()->id)) {
            return $this->errorJson('无权操作');
        }

        if (!$department = Department::business()->find(\request()->id)) {
            return $this->errorJson('请选择要编辑的部门');
        }
        $order = intval(\request()->order) ?: null;
        $res = DepartmentService::changeDepartment(\request()->name, $department->parent_id, $department->id, $order);
        if (!$res['result']) {
            return $this->errorJson($res['msg']);
        }

        return $this->successJson('编辑成功');

    }


    /*
     * 删除部门
     */
    public function deleteDepartment()
    {
        if (!DepartmentService::checkDepartmentIdByMethod('deleteDepartment', \request()->id)) {
            return $this->errorJson('无权操作');
        }

        $res = DepartmentService::deleteDepartment(\request()->id);
        if (!$res['result']) {
            return $this->errorJson($res['msg']);
        }

        return $this->successJson('删除成功');
    }


    /*
     * 推送部门列表到企业微信
     */
    public function pushDepartment()
    {
        try {
            if (!SettingService::EnabledQyWx()) {
                throw new Exception('未开启企业微信');
            }

            $department = Department::business()->with('hasOneParentDepartment')->orderBy('level', 'ASC')->get();

            $department->each(function ($v) {

                if ($v->level == 1 && $v->wechat_department_id == 0) {
                    throw new Exception('一级部门未关联企业微信');
                }

                $res = DepartmentService::pushDepartment($v);
                if (!$res['result']) {
                    throw new Exception($res['msg']);
                }
            });
        } catch (Exception $e) {
            return $this->errorJson($e->getMessage());
        }

        return $this->successJson('推送成功');

    }
}
