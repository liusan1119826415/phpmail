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

use app\common\facades\Setting;
use app\common\models\Member;
use app\common\services\Session;
use business\common\models\BusinessApply;
use business\common\models\Department;
use business\common\models\ManagerList;
use business\common\models\PlatLog;
use business\common\models\Staff;
use business\common\services\BusinessService;
use business\common\services\SettingService;
use business\common\controllers\components\BaseController;
use business\common\models\Business as BusinessModel;
use Illuminate\Support\Facades\Schema;
use Yunshop\YunSign\common\models\CompanyAccount;
use Yunshop\YunSign\common\models\PersonAccount;

class BusinessController extends BaseController
{


    /*
     * 公用信息接口
     */
    public function getBusinessCommonData()
    {
        $member = Member::uniacid()->select('nickname', 'realname', 'avatar', 'uid', 'mobile')->find(\YunShop::app()->getMemberId());
        $right = [];
        if (\request()->is_business) {
            if (!$business_id = SettingService::getBusinessId()) {
                return $this->errorJson(BusinessService::BUSINESS_LIST_MSG, BusinessService::getBusinessListReturn());
            }
            $right = BusinessService::checkBusinessRight();
        }
        if (request()->is_person) {
            $right = BusinessService::checkPersonRight();
        }
        return $this->successJson('成功', [
            'member' => $member,
            'identity' => $right['identity'] ?: 0,
            'tab' => $right['page'] ?: [],
            'page_route' => $right['page_route'] ?: [],
            'plat_setting' => array_merge(\Yunshop\BusinessPc\services\SettingService::getSetting(), \Yunshop\BusinessPc\services\SettingService::getCustomerSetting()),
            'business' => [
                'business_name' => $right['business_name'] ?: '',
                'business_id' => $right['business_id'] ?: 0,
                'uniacid' => \YunShop::app()->uniacid,
            ],
            'pos_goods_id'=>app('plugins')->isEnabled('shop-pos') && ($pos_goods_id = \Yunshop\ShopPos\services\SettingService::getSetting()['bind_goods_id']) ? $pos_goods_id : 0,
        ]);
    }

    public function pluginEnabled()
    {
        $auth_plugins = SettingService::getEnablePlugins(SettingService::getBusinessId());

        $project_manager = app('plugins')->isEnabled('project-manager') && in_array('ProjectManager', $auth_plugins);
        $data['project_manager'] = $project_manager?1:0;



        return $this->successJson('权限',$data);
    }


    /*
    * 管理企业
    */
    public function manageBusiness()
    {
        if (request()->identity_type) {//个人空间
            $plat_log = PlatLog::where('uid', \YunShop::app()->getMemberId())->first();
            if ($plat_log) {
                $plat_log->update([
                    'final_plat_id' => 0,
                ]);
            } else {
                $create_arr = [
                    'uniacid' => \YunShop::app()->uniacid,
                    'uid' => \YunShop::app()->getMemberId(),
                    'final_plat_id' => 0,
                ];
                PlatLog::create($create_arr);
            }
            Session::clear('business_id');
            return $this->successJson('成功');
        }
        $business_id = \request()->id ?: 0;
        $res = BusinessService::checkBusinessRight($business_id);
        if (!$res['identity']) {
            return $this->errorJson('无权管理该企业');
        }
        SettingService::setBusinessId($business_id);
        return $this->successJson('成功');
    }


    /*
     * 企业管理列表
     */
    public function businessList()
    {
        $uid = \YunShop::app()->getMemberId();

        $where = [
            ['status', BusinessModel::STATUS_NORMAL],
            ['member_uid', $uid]
        ];

        $creater_id_list = BusinessModel::uniacid()->where($where)->pluck('id')->toArray(); // 查询为创始人的企业id
        $manager_id_list = ManagerList::where('uid', $uid)->pluck('business_id')->toArray(); // 查询为管理员的企业id
        $staff_id_list = Staff::where('uid', $uid)->where('disabled', 0)->pluck('business_id')->toArray(); //查询为员工的企业id
		$owner_id_list = [];
		if (app('plugins')->isEnabled('yun-sign') && Schema::hasColumn('yz_yun_sign_company_account', 'cid')) {
            $owner_id_list = CompanyAccount::where('uid', $uid)->pluck('cid')->toArray(); // 查询为法人的企业id
        }
        $platform_list = BusinessService::formPlatList($creater_id_list, $owner_id_list, $manager_id_list, $staff_id_list); //组装企业列表
        $final_plat_id = PlatLog::getPlatLogId(); // 获取用户最后进行管理的企业

        return $this->successJson('成功', [
            'platform_list' => $platform_list,
            'final_plat_id' => $final_plat_id
        ]);

    }


    /*
     * 创建企业
     */
    public function addBussiness()
    {
        $request = \request();

        if (!$request->name) {
            return $this->errorJson('请输入企业名');
        }

        if (!$request->logo_img) {
            return $this->errorJson('请上传企业logo');
        }


        if (!SettingService::needExamine()){

            $business = BusinessModel::create([
                'uniacid' => \YunShop::app()->uniacid,
                'member_uid' => \YunShop::app()->getMemberId(),
                'name' => trim($request->name),
                'logo_img' => trim($request->logo_img),
                'auth_plugins' => serialize((Setting::get('plugin.work-wechat-platform.auth_plugins') ?: [])),
            ]);

            if (!$business) {
                return $this->errorJson('创建企业失败');
            }

            Department::create([
                'uniacid' => \YunShop::app()->uniacid,
                'business_id' => $business->id,
                'name' => $business->name,
                'en_name' => '',
                'level' => 1,
                'parent_id' => 0,
                'wechat_department_id' => 0,
                'order' => 9999
            ]);

        }else{
            BusinessApply::create([
                'uniacid' => \YunShop::app()->uniacid,
                'uid' => \YunShop::app()->getMemberId(),
                'name' => trim($request->name),
                'logo_img' => trim($request->logo_img),
                'status'=>BusinessApply::STATUS_WAIT,
            ]);
        }
        return $this->successJson('提交申请成功，请等待平台审核');

    }


}
