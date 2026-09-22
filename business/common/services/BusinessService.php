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


use app\common\models\Member;
use business\admin\menu\BusinessMenu;
use business\common\models\Business;
use business\common\models\Business as BusinessModel;
use business\common\models\BusinessApply;
use business\common\models\ManagerList;
use app\common\helpers\Cache;
use business\common\models\Staff;

class BusinessService
{

    const REDIS_KEY = 'qy_business';
    const BUSINESS_LIST_MSG = '请先选择要管理的企业';
    const LOGIN_MSG = '请登录';


    /*
     * 组装平台列表数据
     */
    public static function formPlatList($creater_id_list = [], $owner_id_list = [], $manager_id_list = [], $staff_id_list = [])
    {
        $business_id_list = array_values(array_unique(array_filter(array_merge($creater_id_list, $owner_id_list, $manager_id_list, $staff_id_list))));

        $platform_list = BusinessModel::uniacid()->select('id', 'logo_img', 'name')
            ->where('status', BusinessModel::STATUS_NORMAL)->whereIn('id', $business_id_list)->get();

        if ($platform_list->isNotEmpty()) {
            $name = empty(request()->name) && request()->name !== 0 && request()->name !== '0' ? '' : request()->name;
            $platform_list = $platform_list->map(function ($v) use ($creater_id_list, $owner_id_list, $manager_id_list, $staff_id_list, $name) {

                if ($name !== '' && !strstr($v->name, $name)) return null;

                $v->logo_img = yz_tomedia($v->logo_img);
                if (in_array($v->id, $owner_id_list)) {
                    $v->identity = in_array($v->id, $creater_id_list) ? 5 : 4;
                } elseif (in_array($v->id, $creater_id_list)) {
                    $v->identity = 3;
                } elseif (in_array($v->id, $manager_id_list)) {
                    $v->identity = 2;
                } elseif (in_array($v->id, $staff_id_list)) {
                    $v->identity = 1;
                } else {
                    return null;
                }
                $v->identity_desc = BusinessModel::IDENTITY_DESC[$v->identity];
                $v->identity_type = 2;
                return $v;
            });
        }
        if (app('plugins')->isEnabled('yun-sign')) {
            $member = \app\frontend\models\Member::current();
            $person = [
                'id' => 0,
                'logo_img' => $member->avatar,
                'name' => $member->nickname,
                'identity' => 9999,
                'identity_desc' => '个人空间',
                'identity_type' => 1, //1个人 2企业
            ];
            $person = collect($person);
            $platform_list->push($person);
        }
        $platform_list = $platform_list->filter();
        $platform_list = $platform_list->sortByDesc('identity')->values();

        $apply_list = BusinessApply::uniacid()->select('id','name','logo_img')->uid(\YunShop::app()->getMemberId())->status(0)->get();
        if ($apply_list){
            $apply_list->each(function ($apply)use($platform_list){
                $apply->identity = 0;
                $apply->identity_desc = '待审核';
                $apply->identity_type = 3;
                $platform_list->push($apply);
            });
        }

        return $platform_list;
    }

    /*
   * 获取用户是否拥有某个接口的权限
   */
    public static function getPremissionByRoute($route, $module = 'admin')
    {
        $right_arr = self::checkBusinessRight();

        $can = 0;
        foreach ($right_arr['route'][$module] as $v) {
            if ($v['route'] == $route) {
                $can = $v['can'];
                break;
            }
        }

        return $can;
    }

    /*
     * 获取登录异常提示
     */
    public static function getLoginReturn()
    {
        return [
            'extra' => '',
            'i' => \YunShop::app()->uniacid,
            'login_status' => 1,
            'login_url' => self::getLoginUrl(),
            'mid' => \request()->mid,
            'scope' => '',
            'type' => 5
        ];
    }

    /*
   * 获取企业管理异常提示
   */
    public static function getBusinessListReturn()
    {
        return [
            'extra' => '',
            'i' => \YunShop::app()->uniacid,
            'login_status' => 2,
            'login_url' => self::getBusinessListUrl(),
            'mid' => \request()->mid,
            'scope' => '',
            'type' => 5
        ];
    }

    /*
     * 获取登录页面链接
     */
    public static function getLoginUrl()
    {
        return yzBusinessFullUrl('login', ['i' => \YunShop::app()->uniacid]);
    }


    /*
    * 获取平台列表页面链接
    */
    public static function getBusinessListUrl()
    {
        return yzBusinessFullUrl('business/index', ['i' => \YunShop::app()->uniacid]);
    }


    /*
     *确认企业是否已经认证
     */
    public static function checkBusinessAuth($id)
    {
        if (app('plugins')->isEnabled('yun-sign')) {
            if ($company_account = \Yunshop\YunSign\common\models\CompanyAccount::where('cid', $id)->first()) {
                if ($company_account->status == 1) {
                    return true;
                }
            }
        }
        return false;
    }


    /*
    * 获取企业创建人uid
    */
    public static function getBusinessCreater($business_id = 0, $member_id = 0)
    {
        $business_id = $business_id ?: SettingService::getBusinessId();
        $business = BusinessModel::with(['hasOneMember' => function ($query) {
            $query->select('nickname', 'realname', 'avatar', 'uid', 'mobile');
        }])->find($business_id);

        if ($member_id) {
            return $member_id == $business->member_uid;
        }

        return $business->hasOneMember;
    }

    /*
    * 获取企业法人
    */
    public static function getBusinessOwner($business_id = 0, $member_id = 0)
    {
        if (!app('plugins')->isEnabled('yun-sign') || !$business_id = $business_id ?: SettingService::getBusinessId()) {
            return null;
        }

        $company_account = \Yunshop\YunSign\common\models\CompanyAccount::uniacid()->where('cid', $business_id)->where('status', 1)->first();

        if ($member_id) {
            return $company_account->uid == $member_id;
        }

        return $company_account->uid ? Member::uniacid()->find($company_account->uid) : null;
    }

    /*
     * 获取管理员
     */
    public static function getManager($business_id = 0, $member_id = 0)
    {
        $business_id = $business_id ?: SettingService::getBusinessId();
//        $member_id = $member_id ?: \YunShop::app()->getMemberId();

        $where = [['business_id', $business_id]];
        $with = [
            'hasOneStaff' => function ($query) use ($business_id) {
                $query->where('business_id', $business_id);
            },
            'hasOneMember' => function ($query) {
                $query->select('nickname', 'realname', 'avatar', 'uid', 'mobile');
            }
        ];

        if ($member_id) {
            $where[] = ['uid', $member_id];
            $manager_list = ManagerList::where($where)->with($with)->first();
            return $manager_list->hasOneStaff->uid ? $manager_list : null;
        }


        $manager_list = ManagerList::where($where)->with($with)->get();

        $manager_list = $manager_list->map(function ($v) {
            if ($v->hasOneStaff->uid) return $v;
        });

        return $manager_list->filter();
    }

    /*
     * 获取员工
     */
    public static function getStaff($business_id = 0, $member_id = 0)
    {
        $business_id = $business_id ?: SettingService::getBusinessId();
        $member_id = $member_id ?: \YunShop::app()->getMemberId();
        return Staff::where('business_id', $business_id)->where('disabled', 0)->where('uid', $member_id)->first();
    }

    public static function getModuleRedisKey($uniacid)
    {
        return self::REDIS_KEY . '_uniacidId_' . $uniacid;
    }

    public static function getBusinessRedisKey($business_id)
    {
        return self::REDIS_KEY . '_businessId_' . $business_id;
    }

    public static function getMemberRedisKey($member_id)
    {
        return self::REDIS_KEY . '_memberId_' . $member_id;
    }


    /*
     * 生成用户权限数据
     * identity：1员工  2管理员  3创建人 4法人 5创建人+法人
     */
    public static function checkBusinessRight($business_id = 0, $member_id = 0, $forget = 0)
    {
        $member_id = $member_id ?: \YunShop::app()->getMemberId();
        $business_id = $business_id ?: SettingService::getBusinessId();
        if (!$member_id || !$business_id) {
            return [];
        }

        $key = 'BusinessRight_MemberId' . $member_id . '_BusinessId' . $business_id;

        if ($forget) {
            self::flush(0, $member_id);
        }

        if ($return_data = Cache::get($key, null, [self::getModuleRedisKey(\YunShop::app()->uniacid), self::getBusinessRedisKey($business_id), self::getMemberRedisKey($member_id)])) {
            return $return_data;
        }

        $business = Business::uniacid()->find($business_id);

        $return_data = [
            'business_id' => $business_id,
            'member_id' => $member_id,
            'business_name' => $business->name ?: '',
        ];

        if (self::getBusinessOwner($business_id, $member_id)) { //如果是法人
            $return_data['identity'] = self::getBusinessCreater($business_id, $member_id) ? 5 : 4; //判断是否同时是法人和创始人
        } elseif (self::getBusinessCreater($business_id, $member_id)) { //如果是创始人
            $return_data['identity'] = 3;
        } elseif (self::getManager($business_id, $member_id)) {  //如果是管理员
            $return_data['identity'] = 2;
        } elseif (self::getStaff($business_id, $member_id)) {  //如果是员工
            $return_data['identity'] = 1;
        }

        $return_data['is_cunstom'] = SpecialCheckService::isCustom($business_id, $member_id, $forget);

        if ($return_data['identity']) {
            $menu = SettingService::getMenu($business_id);
            $all_right = $return_data['identity'] > 1 ? 1 : 0;
            $return_data['route'] = self::checkBusinessRouteRight($menu['route'], $business_id, $member_id, $return_data); //获取路由权限
            $return_data['page_route'] = self::checkBusinessRouteRight($menu['page_route'], $business_id, $member_id, $return_data); //获取页面路由权限
            $return_data['page'] = self::checkBusinessPageRight($menu, $business_id, $member_id, $return_data);  //获取页面侧边栏
            $department_id_arr = (new DepartmentPremissionService($business_id, $member_id))->getAllPremissionDepartmentId($all_right); //获取部门管理相关接口可用部门id
            $return_data['route_department_id'] = $department_id_arr ?: [];

            Cache::put($key, $return_data, 60, [self::getModuleRedisKey(\YunShop::app()->uniacid), self::getBusinessRedisKey($business_id), self::getMemberRedisKey($member_id)]);
        }

        return $return_data;
    }

    public static function checkPersonRight()
    {
        $menu_arr = \app\common\modules\shop\ShopConfig::current()->get('business_plugin_menu.YunSignPerson') ?: []; //获取插件路由和页面
        $class = $menu_arr['class'];
        $function = $menu_arr['function'];
        if (!method_exists($class, $function)) {
            return [];
        }
        $menuService = new BusinessMenu();
        $menu = $class::$function();
        $this_route = $menuService->getRoute($menu, 'YunSignPerson');
        $this_page = $menuService->getMenuPage($menu, 'YunSignPerson');
        $this_page = array_values(self::handlePage($this_page));
        $page_route = $menuService->getPageRoute($menu, 'YunSignPerson');
        $return_menu = ['route' => $this_route, 'page' => $this_page, 'page_route' => $page_route];
        return $return_menu;
    }

    public static function handlePage($this_page)
    {
        foreach ($this_page as $key => $item) {
            $this_page[$key]['name'] = $this_page[$key]['tab_name'];
            unset($this_page[$key]['tab_name']);
            unset($this_page[$key]['premit']);
            unset($this_page[$key]['module']);
            unset($this_page[$key]['identity']);
            unset($this_page[$key]['page_name']);
            unset($this_page[$key]['special_check']);
            if ($item['child']) {
                $item['child'] = array_values($item['child']);
                $this_page[$key]['child'] = self::handlePage($item['child']);
            }
        }
        return $this_page;
    }

    /*
     * 确认用户页面权限
     */
    public static function checkBusinessPageRight($menu = [], $business_id = 0, $uid = 0, $auth = [])
    {

        $menu = $menu ?: SettingService::getMenu();
        $page = $menu['page'] ?: [];
        $class = (new DepartmentPremissionService($business_id, $uid));
        $right_arr = $class->getRightArr();

        $return_data = [];
        foreach ($page as $k => $v) {
            if ($res = self::foreachPage($v, $right_arr, $auth)) {
                $return_data = array_merge($return_data, $res);
            }
        }

        return $return_data;
    }

    /*
     * 递归处理页面权限数据
     */
    public static function foreachPage(&$page_menu, &$right_arr, &$auth)
    {

        $identity = $auth['identity'];

        foreach ($page_menu as $k => $v) {

            if ($v['identity'] && $identity < $v['identity']) {
                unset($page_menu[$k]);
                continue;
            }

            if ($v['premit'] == 1 && $identity < 2 && $right_arr->where('route', $v['route'])->where('module', $v['module'])->isEmpty()) {
                unset($page_menu[$k]);
                continue;
            }

            if ($v['premit'] == 1 && $v['special_check']) {  //如果不是客服账号，屏蔽芸客服相关路由
                if ($v['special_check'] == 'isCustom' && !$check_res = SpecialCheckService::isCustom($auth['business_id'], $auth['member_id'])) {
                    unset($page_menu[$k]);
                    continue;
                }
                if ($v['special_check'] == 'onlyBind' && !$check_res = SpecialCheckService::onlyBind($auth['business_id'])) {
                    unset($page_menu[$k]);
                    continue;
                }
            }


            $page_menu[$k] = [
                'name' => $v['tab_name'],
                'route' => $v['route'],
                'child' => []
            ];

            if ($v['child']) {
                $page_menu[$k]['child'] = self::foreachPage($v['child'], $right_arr, $auth);
            }

        }
        $page_menu = array_values($page_menu);
        return $page_menu;
    }


    /*
     * 确认用户路由权限
     */
    public static function checkBusinessRouteRight($route = [], $business_id = 0, $uid = 0, $auth = [])
    {

        $identity = $auth['identity'];
        $all_right = $identity > 1 ? 1 : 0;

        $route = $route ?: [];
        $business_id = $business_id ?: SettingService::getBusinessId();
        $uid = $uid ?: \YunShop::app()->uniacid;

        if ($all_right) { //如果是管理员、企业主、法人，获取所有权限
            foreach ($route as $k => $v) {

                foreach ($route[$k] as $kk => &$vv) {

                    $vv['can'] = 1;
                    if ($identity < $vv['identity']) { //判断当前用户身份等級是否满足需求
                        $vv['can'] = 0;
                    }
                    if (!$vv['can'] || !$vv['special_check']) {
                        continue;
                    }
                    switch ($vv['special_check']) {
                        case 'isCustom':  //判断是否客服
                            $vv['can'] = SpecialCheckService::isCustom($business_id, $uid) ? $vv['can'] : 0;
                            break;
                        case 'onlyBind':
                            $vv['can'] = SpecialCheckService::onlyBind($business_id) ? $vv['can'] : 0;
                            break;
                    }

                }
            }
        } else {
            $class = (new DepartmentPremissionService($business_id, $uid));
            $right_arr = $class->getRightArr();  //获取允许访问的路由

            if (!$right_arr || $right_arr->isEmpty()) {
                return $route;
            }

            foreach ($route as $k => $v) {
                foreach ($route[$k] as $kk => &$vv) {

                    if ($right_arr->where('route', $vv['route'])->isNotEmpty()) {
                        $vv['can'] = 1;
                    }
                    if ($vv['identity'] && $identity < $vv['identity']) {
                        $vv['can'] = 0;
                    }
                    if (!$vv['can'] || !$vv['special_check']) {
                        continue;
                    }
                    switch ($vv['special_check']) {
                        case 'isCustom':
                            $vv['can'] = SpecialCheckService::isCustom($business_id, $uid) ? $vv['can'] : 0;
                            break;
                        case 'onlyBind':
                            $vv['can'] = SpecialCheckService::onlyBind($business_id) ? $vv['can'] : 0;
                            break;
                    }

                }
            }
        }
        return $route;
    }


    /*
     * 清除企业相关缓存
     */
    public static function flush($business_id = 0, $member_id = 0, $uniacid = 0)
    {

        $tags = [];
        if ($business_id) {
            if (is_array($business_id)) {
                foreach ($business_id as $v) {
                    $tags[] = self::getBusinessRedisKey($v);
                }
            } else {
                $tags[] = self::getBusinessRedisKey($business_id);
            }
        }

        if ($member_id) {
            if (is_array($member_id)) {
                foreach ($member_id as $v) {
                    $tags[] = self::getMemberRedisKey($v);
                }
            } else {
                $tags[] = self::getMemberRedisKey($member_id);
            }
        }

        if ($uniacid) {
            if (is_array($uniacid)) {
                foreach ($uniacid as $v) {
                    $tags[] = self::getModuleRedisKey($v);
                }
            } else {
                $tags[] = self::getModuleRedisKey($uniacid);
            }
        }

        if ($tags) {
            Cache::flush($tags);
        }

    }


}