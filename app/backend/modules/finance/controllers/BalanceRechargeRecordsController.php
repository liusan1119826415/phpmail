<?php
/****************************************************************
 * Author:  libaojia
 * Date:    2017/12/13 下午2:25
 * Email:   livsyitian@163.com
 * QQ:      995265288
 * User:
 ****************************************************************/

namespace app\backend\modules\finance\controllers;


use app\backend\modules\member\models\MemberGroup;
use app\backend\modules\member\models\MemberLevel;
use app\backend\modules\finance\models\BalanceRechargeRecords;
use app\common\components\BaseController;
use app\common\facades\Setting;
use app\common\helpers\PaginationHelper;
use app\exports\traits\ExportTrait;


class BalanceRechargeRecordsController extends BaseController
{

    use ExportTrait;

    public function index()
    {
        return view('finance.balance.rechargeRecord')->render();
    }

    public function getData()
    {
        $records = BalanceRechargeRecords::records();

        $search = \YunShop::request()->search;
        if ($search) {
            $records = $records->search($search);
        }

        $amount = $records->sum('money');

        $recordList = $records->orderBy('created_at', 'desc')->paginate()->toArray();
        $shopSet = Setting::get('shop.member');
        $shopSet['level_name'] = $shopSet['level_name'] ?: '普通会员';

        $balance_invoice_switch = app('plugins')->isEnabled('balance-invoice') && \Setting::get('plugin.balance_invoice')['plugin_switch'];

        foreach ($recordList['data'] as &$item) {

            //是否支持开发票
            $item['open_invoice'] = 0;
            if ($balance_invoice_switch &&
                !in_array($item['type'], [0,5,8,17]) && $item['status'] >= 1 && $item['is_invoice'] != 2) {
                //过滤 后台付款、货到付款、现金支付、现金支付
                $item['open_invoice'] = 1;
            }

            if ($item['member']) {
                $item['member']['avatar'] = $item['member']['avatar'] ? tomedia($item['member']['avatar']) : tomedia($shopSet['headimg']);
                $item['member']['nickname'] = $item['member']['nickname'] ?:
                    ($item['member']['mobile'] ? substr($item['member']['mobile'], 0, 2) . '******' . substr($item['member']['mobile'], -2, 2) : '无昵称会员');
                $item['member']['yz_member']['level'] = $item['member']['yz_member']['level'] ?: '';
                $item['member']['yz_member']['group'] = $item['member']['yz_member']['group'] ?: '';
            } else {
                $item['member'] = [
                    'avatar' => tomedia($shopSet['headimg']),
                    'nickname' => '该会员已被删除或者已注销',
                    'yz_member' => [
                        'level' => '',
                        'group' => ''
                    ]
                ];
            }
        }


        //支付类型：1后台支付，2 微信支付 3 支付宝， 4 其他支付
        return $this->successJson('ok', [
            'shopSet'     => $shopSet,
            'recordList'  => $recordList,
            'memberGroup' => MemberGroup::getMemberGroupList(),
            'memberLevel' => MemberLevel::getAllMemberLevelList(),
            'payType'     => BalanceRechargeRecords::$typeComment,
            'search'      => $search,
            'amount'      => $amount,
        ]);
    }

}
