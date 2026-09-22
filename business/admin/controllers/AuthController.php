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

use business\common\models\Staff;
use business\common\services\AuthService;
use business\common\services\BusinessService;
use business\common\services\SettingService;
use business\common\controllers\components\BaseController;


/**
 * controller基类
 *
 * Author:
 * Date: 21/02/2017
 * Time: 21:20
 */
class AuthController extends BaseController
{


    /*
   * 设置成员权限
   */
    public function setStaffAuthType()
    {
        $right = BusinessService::checkBusinessRight();
        if ($right['identity'] < 2) {
            return $this->errorJson('无权设置成员权限');
        }

    }


    /*
     * 获取权限列表
     */
    public function getAuthList()
    {

        $right = BusinessService::checkBusinessRight();
        if ($right['identity'] < 2) {
            return $this->errorJson('无权操作');
        }

        $request = \request();
        $list = AuthService::getAuthList($request->department_id ?: 0, $request->is_leader ? 1 : 0, $request->staff_id ?: 0);
        if (!$list['result']) {
            return $this->errorJson($list['msg']);
        }

        $auth_list = $list['data'];
        $plugin_list = SettingService::getPluginList();
        $tab_list = [
            ['key' => 'admin', 'name' => '基础权限'],
        ];
        foreach ($plugin_list as $k => $v) {
            if (app('plugins')->isEnabled($k)) $tab_list[] = $v;
        }

        if ($request->staff_id) {
            $staff = Staff::business()->find($request->staff_id);
            $right_type = $staff->right_type;
        } else {
            $right_type = 0;
        }

        return $this->successJson('成功', ['auth_list' => $auth_list, 'tab_list' => $tab_list, 'right_type' => $right_type]);

    }

    /*
     * 设置权限
     */
    public function setAuth()
    {
        $request = \request();
        $res = AuthService::setAuth($request->auth, $request->department_id ?: 0, $request->is_leader ? 1 : 0, $request->staff_id ?: 0, $request->right_type ?: 0);
        if (!$res['result']) {
            return $this->errorJson($res['msg']);
        }

        BusinessService::flush(SettingService::getBusinessId()); //清除企业缓存

        return $this->successJson('编辑成功');
    }

}
