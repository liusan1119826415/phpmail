<?php
/****************************************************************
 * Author:  libaojia
 * Date:    2017/11/14 上午10:22/2019-06-28 下午14:47
 * Email:   livsyitian@163.com
 * QQ:      995265288
 * User:
 * Tool:    Created by PhpStorm.
 ****************************************************************/

namespace app\backend\modules\withdraw\controllers;


use app\backend\modules\withdraw\models\WithdrawModel as Withdraw;
use app\backend\modules\member\models\MemberBankCard;
use app\backend\modules\member\models\MemberShopInfo;
use app\common\components\BaseController;
use app\common\facades\Setting;
use app\common\helpers\PaginationHelper;
use app\common\models\WithdrawMergeServicetaxRate;
use app\common\services\ExportService;
use app\exports\traits\ExportTrait;

class RecordsController extends BaseController
{

    use ExportTrait;

    /**
     * @var Withdraw
     */
    private $withdrawModel;
    private $amount;

    public function __construct()
    {
        parent::__construct();

        $this->withdrawModel = Withdraw::records();
    }

    //全部记录
    public function index()
    {
        $this->searchRecords();

        return $this->view();
    }

    //待审核记录
    public function initial()
    {
        $this->withdrawModel->initial();

        return $this->index();
    }

    //待打款记录
    public function audit()
    {
        $this->withdrawModel->audit();

        return $this->index();
    }

    //打款中记录
    public function paying()
    {
        $this->withdrawModel->paying();

        return $this->index();
    }

    //已打款记录
    public function payed()
    {
        $this->withdrawModel->payed();

        return $this->index();
    }

    //已驳回记录
    public function rebut()
    {
        $this->withdrawModel->rebut();

        return $this->index();
    }

    //已无效记录
    public function invalid()
    {
        $this->withdrawModel->invalid();

        return $this->index();
    }

    /**
     * 视图和页面数据
     *
     */
    private function view()
    {
        if (request()->ajax()) {
            return $this->successJson('ok', $this->resultData());
        }
        return view('withdraw.records');
    }

    /**
     * @return array
     */
    private function resultData()
    {

        $records = $this->withdrawModel
            ->orderBy('id','desc')
            ->paginate()->toArray();

        $shopSet = Setting::get('shop.member');
        foreach ($records['data'] as &$item) {
            $item['has_one_member']['avatar'] =  $item['has_one_member']['avatar'] ? tomedia($item['has_one_member']['avatar'] ) : tomedia($shopSet['headimg']);
            $item['has_one_member']['nickname'] = $item['has_one_member']['nickname'] ?: '未更新';
        }

        return [
            'records' => $records,
            'search' => $this->searchParams(),
            'types' => Withdraw::getTypes(),
            'pay_way_list' => Withdraw::getPayWay(),
            'amount' => $this->amount
        ];
    }

    /**
     * 记录搜索
     */
    private function searchRecords()
    {
        $search = $this->searchParams();
        if ($search) {
            $search['searchtime'] = is_numeric($search['time']['start']) && is_numeric($search['time']['end']);
            $this->withdrawModel->search($search);
        }
        $this->withdrawModel->orderBy('created_at', 'desc');
        $this->amount = $this->withdrawModel->sum('amounts');
    }

    /**
     * @return array
     */
    private function searchParams()
    {
        $search = \YunShop::request()->search;

        return $search ?: [];
    }



}
