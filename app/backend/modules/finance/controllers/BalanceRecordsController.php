<?php
/****************************************************************
 * Author:  libaojia
 * Date:    2017/7/11 下午9:25
 * Email:   livsyitian@163.com
 * QQ:      995265288
 * User:
 ****************************************************************/

namespace app\backend\modules\finance\controllers;


use app\backend\modules\finance\models\Balance;
use app\common\components\BaseController;
use app\common\exceptions\ShopException;
use app\common\facades\Setting;
use app\common\helpers\PaginationHelper;
use app\common\models\MemberLevel;
use app\common\services\credit\ConstService;
use app\common\services\ExportService;
use app\common\services\member\group\GroupService;
use app\common\services\member\level\LevelService;
use app\exports\traits\ExportTrait;

class BalanceRecordsController extends BaseController
{
    use ExportTrait;

    const PAGE_SIZE = 20;

    public function index()
    {
        $source_name = $this->getServiceType();
        $source_name_show = [];
        foreach ($source_name as $key => $item) {
            array_push($source_name_show, [
                'id' => $key,
                'value' => $item,
            ]);
        }
        return view('finance.balance.balanceRecords', [
            'head_img' => yz_tomedia($this->getShopSet()['headimg']),
            'source_name' => json_encode($source_name_show),
            'member_levels' => json_encode($this->getMemberList()),
            'member_groups' => json_encode($this->getMemberGroup()),
        ])->render();
    }

    public function search()
    {
        $records = Balance::records();
        $search = $this->getPostSearch();
        if (request()->ajax()) {
            $records = $records->search($search);
            if ($search['member'] || ($search['member_level'] !== '' && $search['member_level'] !== null) || $search['member_group']) {
                $records = $records->searchMember($search);
            }
        }
        $records->select('yz_balance.*');
        $amount = $records->sum('change_money');
        $pageList = $records->orderBy('yz_balance.id', 'desc')->paginate(static::PAGE_SIZE);
        return $this->successJson('ok', [
            'list' => $pageList,
            'amount' => $amount,
        ]);
    }

    private function getPostSearch()
    {
        return \YunShop::request()->search;
    }

    private function getShopSet()
    {
        return Setting::get('shop.member');
    }

    private function getServiceType()
    {
        return (new ConstService(''))->sourceComment();
    }

    private function getMemberList()
    {
        return MemberLevel::getAllMemberLevelList();
    }

    private function getMemberGroup()
    {
        return GroupService::getMemberGroupList();
    }
}
