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

use Exception;
use EasyWeChat\Factory;
use app\common\facades\EasyWeChat;

class QyWechatRequestService
{

    public $business_id;
    public $config;
    public $easyWechat;
    public $base_data;
    public $callback_data;
    public $return_data;
    public $return_columns_handle;
    public $return_columns;
    public $unset_columns;
    public $function;
    public $require_data;
    public $function_list = [
        'getDepartmentList' => ['name' => '获取部门列表'],
        'createDepartment' => ['name' => '创建部门'],
        'updateDepartment' => ['name' => '修改部门'],
        'deleteDepartment' => ['name' => '删除部门'],
        'getStaffList' => ['name' => '获取部门成员列表'],
        'getStaffDetailList' => ['name' => '获取部门成员详情列表'],
        'getStaffDetail' => ['name' => '获取部门成员详情'],
        'createMember' => ['name' => '创建成员'],
        'updateMember' => ['name' => '编辑成员'],
        'deleteMember' => ['name' => '删除成员'],
        'getMediaId' => ['name' => '上传临时素材'],
        'getStaffTicket'=>['name'=>'成员个人数据授权'],
        'getUserDetail'=>['name'=>'成员个人数据详情'],
        'departmentIds'=>['name'=>'获取企业微信部门ID'],
        'departmentDetail'=>['name'=>'获取部门详情'],
    ];


    public function __construct($business_id = 0)
    {
        $business_id = intval($business_id) ?: SettingService::getBusinessId();
        $this->config = SettingService::getQyWxSetting($business_id);
        $column_arr = ['corpid', 'contact_secret'];
        foreach ($column_arr as $v) {
            if (!$this->config[$v]) return;
        }
        $this->easyWechat = EasyWeChat::work([
//        $this->easyWechat = Factory::work([
            'corp_id' => $this->config['corpid'],
            'agent_id' => $this->config['agentid'],
//            'secret' => $this->config['agent_secret'],
            'secret' => $this->config['contact_secret'],
        ],['businessId' => $business_id]);

    }


    public function request($function, $base_data = [])
    {

        try {

            $this->callback_data = [];
            $this->return_data = [];
            $this->return_columns_handle = 0;
            $this->return_columns = [];
            $this->unset_columns = [];

            if (!$this->easyWechat) {
                throw new Exception('尚未配置企业微信信息');
            }

            $function_arr = [
                'getDepartmentList', 'departmentIds', 'departmentDetail',
                'getStaffList', 'getStaffDetailList', 'getStaffDetail',
                'getStaffTicket', 'getUserDetail',];
            if (in_array($function,$function_arr)){
                $this->easyWechat = EasyWeChat::work([
                    'corp_id' => $this->config['corpid'],
                    'agent_id' => $this->config['agentid'],
                    'secret' => $this->config['agent_secret'],
                ]);
            }

            if (!method_exists($this, $function) || !isset($this->function_list[$function])) {
                throw new Exception('未知请求方法');
            }

            $this->base_data = $base_data;
            $this->function = $function;
            $this->$function();
            $this->handleCallback();

        } catch (Exception $e) {
            return ['result' => 0, 'msg' => $this->function_list[$function]['name'] . '失败,' . $e->getMessage(), 'data' => []];
        }

        return ['result' => 1, 'msg' => $this->function_list[$function]['name'] . '成功', 'data' => $this->return_data];

    }

    private function checkRequestData()
    {
        if ($this->require_data) {
            foreach ($this->require_data as $v) {
                if (!isset($this->base_data[$v])) {
                    throw new Exception($v . '为空');
                }
            }
        }

    }

    //返回数据判断处理
    private function handleCallback()
    {

        $err_msg = $this->callback_data['errmsg'] ?: '未知原因';

        if (!$this->callback_data || $this->callback_data['errcode'] !== 0) {
            throw new Exception("请求失败,code:{$this->callback_data['errcode']},msg:{$err_msg}");
        }

        $after_function = 'after' . strtoupper($this->function);
        if (method_exists($this, $after_function)) { //自定义返回数据处理方法
            $this->$after_function();
        } else { //默认返回数据处理方法
            $this->defaultAfter();
        }
    }

    private function defaultAfter($callback_data = [])
    {
        $callback_data = $callback_data ?: $this->callback_data;
        if ($this->return_columns_handle) {
            if ($this->return_columns) {
                foreach ($this->return_columns as $v) {
                    $this->return_data[$v] = $callback_data[$v];
                }
            } elseif ($this->unset_columns) {
                $return_data = $callback_data;
                foreach ($this->unset_columns as $v) {
                    unset($return_data[$v]);
                }
                $this->return_data = $return_data;
            }
        } else {
            $this->return_data = $callback_data;
        }
    }

    private function getUserDetail()
    {
        $this->require_data = ['user_ticket'];
        $this->checkRequestData();
        $this->callback_data = $this->easyWechat->user->getUserDetail(['user_ticket' => $this->base_data['user_ticket']]);
    }

    private function getStaffTicket()
    {
        $this->require_data = ['code'];
        $this->checkRequestData();
        $this->callback_data = $this->easyWechat->user->getUserInfo(['code' => $this->base_data['code']]);
    }


    private function getMediaId()
    {
        $this->require_data = ['path'];
        $this->callback_data = $this->easyWechat->media->uploadImage($this->base_data['path']);
    }

    private function departmentIds()
    {
        $this->callback_data = $this->easyWechat->department->departmentIds();
    }

    private function departmentDetail(){
        $this->callback_data = $this->easyWechat->department->departmentDetail($this->base_data['id']);
    }


    private function getDepartmentList()
    {
        $this->callback_data = $this->easyWechat->department->list();
        $this->return_columns_handle = 1;
        $this->return_columns = ['department'];
    }


    private function getStaffDetailList()
    {
        $this->require_data = ['department_id'];
        $this->checkRequestData();
        $this->callback_data = $this->easyWechat->user->getDetailedDepartmentUsers($this->base_data['department_id'], true);
        $this->return_columns_handle = 1;
        $this->return_columns = ['userlist'];
    }

    private function getStaffDetail()
    {
        $this->require_data = ['userid'];
        $this->checkRequestData();
        $this->callback_data = $this->easyWechat->user->get($this->base_data['userid']);
        $this->return_columns_handle = 1;
        $this->unset_columns = ['errcode', 'errmsg'];
    }


    private function getStaffList()
    {
        $this->require_data = ['department_id'];
        $this->checkRequestData();
        $this->callback_data = $this->easyWechat->user->getDepartmentUsers($this->base_data['department_id'], true);
        $this->return_columns_handle = 1;
        $this->return_columns = ['userlist'];
    }


    private function createDepartment()
    {

        $this->require_data = ['name', 'parent_id'];
        $this->checkRequestData();

        $request_data = [
            'name' => trim($this->base_data['name']),
            'parentid' => $this->base_data['parent_id'],
            'order' => intval($this->base_data['order']) ?: 10000,
        ];
        $this->callback_data = $this->easyWechat->department->create($request_data);
        $this->return_columns_handle = 1;
        $this->return_columns = ['id'];
    }

    private function updateDepartment()
    {
        $this->require_data = ['name', 'parent_id', 'id'];
        $this->checkRequestData();

        $request_data = [
            'name' => trim($this->base_data['name']),
            'order' => intval($this->base_data['order']) ?: 10000,
        ];

        if ($this->base_data['parent_id']) {
            $request_data['parentid'] = $this->base_data['parent_id'];
        }
        $this->callback_data = $this->easyWechat->department->update($this->base_data['id'], $request_data);

    }

    private function deleteDepartment()
    {
        $this->require_data = ['id'];
        $this->checkRequestData();
        $this->callback_data = $this->easyWechat->department->delete($this->base_data['id']);
    }

    private function createMember()
    {
        $this->require_data = ['name', 'userid', 'mobile'];
        $this->checkRequestData();
        $request_data = $this->base_data;
        $this->callback_data = $this->easyWechat->user->create($request_data);

    }

    private function updateMember()
    {
        $this->require_data = ['name', 'userid', 'mobile'];
        $this->checkRequestData();
        $request_data = $this->base_data;
        unset($request_data['userid']);
        $this->callback_data = $this->easyWechat->user->update($this->base_data['userid'], $request_data);
    }

    private function deleteMember()
    {
        $this->require_data = ['userid'];
        $this->checkRequestData();
        $this->callback_data = $this->easyWechat->user->delete($this->base_data['userid']);
    }


}
