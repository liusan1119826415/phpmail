<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2021/9/22
 * Time: 13:37
 */


namespace business\common\services\customers;

use app\common\facades\EasyWeChat;
use Exception;
use EasyWeChat\Factory;
use business\common\services\SettingService;

class WechatCustomerRequestService
{

    public $business_id;
    public $customerWechat;
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
        'getCustomerList' => ['name' => '获取企业客户标签列表'],
        'addTag' => ['name' => '新建标签'],
        'editTag' => ['name' => '修改标签'],
        'deleteTag' => ['name' => '删除标签'],
        'addTagGroup' => ['name' => '新建标签组'],
        'editTagGroup' => ['name' => '修改标签组'],
        'changeMemberTag' => ['name' => '修改客户关联标签'],
        'customerForeverSingleCode' => ['name' => '新增单人联系我(永久)'],
        'deleteCustomerCode' => ['name' => '删除联系我'],
        'sendWelcomeWord' => ['name' => '发送企业客户欢迎语'],
        'addGroupCode' => ['name' => '新增企业微信群邀请码'],
        'editGroupCode' => ['name' => '更新企业微信群邀请码'],
        'deleteGroupCode' => ['name' => '删除企业微信群邀请码'],
        'getGroupCode' => ['name' => '获取企业微信群邀请码'],
    ];


    public function __construct($business_id = 0)
    {
        $business_id = intval($business_id) ?: SettingService::getBusinessId();
        $this->config = SettingService::getQyWxSetting($business_id);
        $column_arr = ['corpid', 'agentid', 'agent_secret'];
        foreach ($column_arr as $v) {
            if (!$this->config[$v]) return;
        }

        /**
         * 用的是应用
         */
        $this->easyWechat = EasyWeChat::work([
            'corp_id' => $this->config['corpid'],
            'agent_id' => $this->config['agentid'],
            'secret' => $this->config['agent_secret'],
        ]);

        /**
         * 用的是通讯录
         */
        $this->customerWechat = EasyWeChat::work([
            'corp_id' => $this->config['corpid'],
            'secret' => $this->config['customer_secret'],
        ]);
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


    protected function getGroupCode()
    {
        $this->callback_data = $this->easyWechat->contact_way->getGroupCode($this->base_data['config_id'] ?: '');
    }


    protected function addGroupCode()
    {
        $params = [
            'scene' => isset($this->base_data['scene']) ? $this->base_data['scene'] : 2,
            'remark' => isset($this->base_data['remark']) ? $this->base_data['remark'] : '',
            'auto_create_room' => isset($this->base_data['auto_create_room']) ? $this->base_data['auto_create_room'] : 1,
            'chat_id_list' => $this->base_data['chat_id_list'],
        ];

        if (isset($this->base_data['state'])) {
            $params['state'] = $this->base_data['state'];
        }

        if ($params['auto_create_room']) {
            $params['room_base_name'] = isset($this->base_data['room_base_name']) ? trim($this->base_data['room_base_name']) : '客户群';
            $params['room_base_id'] = isset($this->base_data['room_base_id']) ? intval($this->base_data['room_base_id']) : 2;
        }

        $this->callback_data = $this->easyWechat->contact_way->addGroupCode($params);
    }

    protected function editGroupCode()
    {
        $params = [
            'config_id' => $this->base_data['config_id'],
            'scene' => isset($this->base_data['scene']) ? $this->base_data['scene'] : 2,
            'remark' => isset($this->base_data['remark']) ? $this->base_data['remark'] : '',
            'auto_create_room' => isset($this->base_data['auto_create_room']) ? $this->base_data['auto_create_room'] : 1,
            'chat_id_list' => $this->base_data['chat_id_list'],
        ];

        if (isset($this->base_data['state'])) {
            $params['state'] = $this->base_data['state'];
        }

        if ($params['auto_create_room']) {
            $params['room_base_name'] = isset($this->base_data['room_base_name']) ? trim($this->base_data['room_base_name']) : '客户群';
            $params['room_base_id'] = isset($this->base_data['room_base_id']) ? intval($this->base_data['room_base_id']) : 2;
        }

        $this->callback_data = $this->easyWechat->contact_way->editGroupCode($params);
    }

    protected function deleteGroupCode()
    {
        $this->callback_data = $this->easyWechat->contact_way->deleteGroupCode($this->base_data['config_id'] ?: '');
        if ($this->callback_data['errcode'] == 41044) {
            $this->callback_data['errcode'] = 0;
        }
    }


    protected function sendWelcomeWord()
    {
        $this->callback_data = $this->easyWechat->external_contact_message->sendWelcome($this->base_data['welcome_code'], $this->base_data);
    }


    protected function customerForeverSingleCode()
    {
        $request_data = [
            'remark' => $this->base_data['remark'] ?: '',
            'skip_verify' => true,
            'state' => $this->base_data['state'] ?: '',
            'user' => $this->base_data['user'],
        ];
        if ($this->base_data['is_temp']) {
            $request_data['is_temp'] = true;
        }
        $this->callback_data = $this->easyWechat->contact_way->create(1, 2, $request_data);
    }


    protected function deleteCustomerCode()
    {
        $this->callback_data = $this->easyWechat->contact_way->delete($this->base_data['config_id']);
        if ($this->callback_data['errcode'] == 41044) {
            $this->callback_data['errcode'] = 0;
        }
    }


    protected function changeMemberTag()
    {
        $this->require_data = ['userid', 'external_userid'];
        $this->checkRequestData();
        if (!$this->base_data['add_tag'] && !$this->base_data['remove_tag']) {
            throw new Exception('请选择要变动的标签');
        }
        $this->callback_data = $this->easyWechat->external_contact->markTags($this->base_data);
    }

    protected function addTag()
    {
        $this->require_data = ['name_arr'];
        $this->checkRequestData();
        if (!$this->base_data['group_id'] && !$this->base_data['group_name']) {
            throw new Exception('请输入标签组信息');
        }
        $request_data = [
            'group_id' => $this->base_data['group_id'],
            'group_name' => $this->base_data['group_name'],
            'order' => intval($this->base_data['order']) ?: 0,
            'tag' => []
        ];
        foreach ($this->base_data['name_arr'] as $k => $v) {
            $request_data['tag'][] = [
                'name' => $v,
                'order' => intval($this->base_data['order_arr'][$k]) ?: 0
            ];
        }
        $this->callback_data = $this->easyWechat->external_contact->addCorpTag($request_data);
        if ($this->callback_data['errcode'] == '40071') {
            throw new Exception('code:40071,msg:企业微信当前标签组下存在同名标签');
        }
        $this->return_columns_handle = 1;
        $this->return_columns = ['tag_group'];
    }


    protected function editTag()
    {
        $this->require_data = ['id', 'name'];
        $this->checkRequestData();
        $order = intval($this->base_data['order']);
        $this->callback_data = $this->customerWechat->external_contact->updateCorpTag($this->base_data['id'], $this->base_data['name'], $order);
        if ($this->callback_data['errcode'] == '40068') {
            throw new Exception('code:40068,msg:请求的企业微信标签不存在或已删除，请先同步企业微信标签,tagId:' . $this->base_data['id']);
        }
    }

    protected function deleteTag()
    {
        if (!$this->base_data['tag_id'] && !$this->base_data['group_id']) {
            throw new Exception('请选择要删除的标签或标签组');
        }
        $this->callback_data = $this->easyWechat->external_contact->deleteCorpTag($this->base_data['tag_id'] ?: [], $this->base_data['group_id'] ?: []);
    }

    protected function getCustomerList()
    {
        $this->callback_data = $this->easyWechat->external_contact->getCorpTags($this->base_data['tag_id'] ?: [], $this->base_data['group_id'] ?: []);
        $this->return_columns_handle = 1;
        $this->return_columns = ['tag_group'];
    }


    protected function checkRequestData()
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
    protected function handleCallback()
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

    protected function defaultAfter($callback_data = [])
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
            } else {
                $this->return_data = $this->callback_data;
            }
        } else {
            $this->return_data = $this->callback_data;
        }
    }

}
