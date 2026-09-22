<?php
/**
 * Created by PhpStorm.
 * 
 *
 *
 * Date: 2021/9/22
 * Time: 13:37
 */

namespace business\frontend\controllers;

use app\common\facades\Setting;
use business\common\models\Department;
use business\common\models\Staff;
use business\common\services\DepartmentService;
use business\common\services\SettingService;
use business\common\services\StaffService;
use business\common\controllers\components\BaseController;

require_once dirname(__FILE__, 3) . "/asset/weworkapi/callback/WXBizMsgCrypt.php";

class WxCallbackController extends BaseController
{

    public $business_id;


    public function __construct()
    {
        parent::__construct();
        $this->preAction();
    }
    
    /**
     * 前置action
     */

    public function preAction()
    {
        $path = \request()->path();
        $path_arr = explode('/', $path);
        if($path_arr[0] == 'business'){
            \YunShop::app()->uniacid = $path_arr[1];
            Setting::$uniqueAccountId = $path_arr[1];
        }else{
            \YunShop::app()->uniacid = $path_arr[0];
            Setting::$uniqueAccountId = $path_arr[0];
        }
		$this->business_id = \request()->business_id;
    }

    public function qyWxCallback()
    {
        $request = \request();

        \Log::debug('企业微信回调开始all', $request->all());
        \Log::debug('企业微信回调开始xml', file_get_contents("php://input"));

        $setting = SettingService::getQyWxSetting($this->business_id);
        if (!$setting['open_state']){
            \Log::debug('企业微信回调失败,平台未开启企业微信同步'.$this->business_id);
            return;
        }

        $request_data = $request->all();

        $msg_signature = $request_data['msg_signature'];
        $timestamp = $request_data['timestamp'];
        $nonce = $request_data['nonce'];
        $echostr = $request_data['echostr'];
        $wxcpt = new \WXBizMsgCrypt($setting['contact_token'], $setting['contact_aes_key'], $setting['corpid']);

        /*url验证*/
        if (!empty($echostr)) {
            $return_str = '';
            $errCode = $wxcpt->VerifyURL($msg_signature, $timestamp, $nonce, $echostr, $return_str);
            if ($errCode == 0) {
                \Log::debug('企业微信回调url验证成功', $return_str);
                echo $return_str;
            } else {
                \Log::debug('企业微信回调url验证失败', [$errCode, $setting]);
            }
            return;
        }
        /*url验证*/


        $request_xml = $request->getContent();
        $xml = "";  // 解析之后的明文
        \Log::debug('企业微信回调request_xml', $request_xml);
        $errCode = $wxcpt->DecryptMsg($msg_signature, $timestamp, $nonce, $request_xml, $xml);

        if ($errCode !== 0) {
            \Log::debug('企业微信回调解密数据失败' . $errCode);
            return;
        }

        if (!$xml) {
            \Log::debug('企业微信回调解密数据失败');
            return;
        }

        $data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA), true), true);
        \Log::debug('企业微信回调解密数据成功', $data);

        SettingService::setBusinessId(\request()->business_id);

        if ($data['MsgType'] == 'event' && $data['Event'] == 'change_contact' && $data['ChangeType']) {
            switch ($data['ChangeType']) {
                case 'update_user':
                    \Log::debug('企业微信回调:成员变更');
                    $res = StaffService::refreshStaff(0, 0, $data['UserID']);
                    break;
                case 'create_user':
                    \Log::debug('企业微信回调:成员新增');
                    $res = StaffService::refreshStaff(0, 0, $data['UserID']);
                    break;
                case 'delete_user':
                    \Log::debug('企业微信回调:成员删除');
                    if (!$staff = Staff::business()->where('user_id', $data['UserID'])->first()) {
                        \Log::debug('企业微信回调删除成员失败:员工不存在');
                        return;
                    }
                    if ($staff->status == 0) {
                        \Log::debug('企业微信回调删除成员失败:员工未与企业微信关联');
                        return;
                    }
                    $staff->status = 0;
                    $staff->save();
                    $res = StaffService::deleteStaff($staff->id);
                    break;
                case 'update_party':
                    \Log::debug('企业微信回调:部门更新');
                    $res = $this->changeDepartment($data);
                    break;
                case 'create_party':
                    \Log::debug('企业微信回调:部门新增');
                    $res = $this->changeDepartment($data);
                    break;
                case 'delete_party':
                    \Log::debug('企业微信回调:部门删除');
                    if (!$department = Department::business()->where('wechat_department_id', $data['Id'])->first()) {
                        \Log::debug('企业微信回调删除部门失败:部门未与企业微信关联');
                    }
                    $res = DepartmentService::deleteDepartment($department->id, 1);
                    break;
                default:
                    \Log::debug('企业微信通知:未知类型的回调');
                    return;
            }
            \Log::debug('企业微信回调处理结果', $res['msg']);
        }


    }


    private function changeDepartment($data)
    {
        if (!$department = Department::business()->where('wechat_department_id', $data['Id'])->first()) {
            $this->dispatch(new \business\common\job\RefreshDepartmentJob(\YunShop::app()->uniacid, $this->business_id));
            return ['result' => 1, 'msg' => '开启部门更新队列成功1'];
        }

        if (isset($data['ParentId'])) {
            if (!$parent_department = Department::business()->where('wechat_department_id', $data['ParentId'])->first()) {
                $this->dispatch(new \business\common\job\RefreshDepartmentJob(\YunShop::app()->uniacid, $this->business_id));
                return ['result' => 1, 'msg' => '开启部门更新队列成功2'];
            }
            $parent_id = $parent_department->id;
        } else {
            $parent_id = $department->parent_id;
        }

        if ($data['Name']) {
            $name = $data['Name'];
        } elseif ($department) {
            $name = $department->name;
        }

        $order = $data['Order'] ?: $department->order;


        if ($parent_id == $department->parent_id) {
            $res = DepartmentService::changeDepartment($name, $parent_id, $department->id, $order, 0);
        } else {
            $this->dispatch(new \business\common\job\RefreshDepartmentJob(\YunShop::app()->uniacid, $this->business_id));
            return ['result' => 1, 'msg' => '开启部门更新队列成功3'];
        }

        return $res;

    }


}
