<?php
/**
 * Created by PhpStorm.
 *
 * User: king/QQ：995265288
 * Date: 2018/5/15 上午9:51
 * Email: livsyitian@163.com
 */

namespace app\backend\modules\income\controllers;


use app\backend\modules\income\models\ChangeAmountLog;
use app\backend\modules\income\models\Income;
use app\common\components\BaseController;
use app\common\facades\Setting;
use app\common\helpers\PaginationHelper;
use app\common\services\ExportService;
use app\exports\traits\ExportTrait;
use DB;

class IncomeRecordsController extends BaseController
{
    use ExportTrait;
    //收入明细
    public function index()
    {
        if (request()->ajax()) {
            $records = Income::records()->withMember()
                ->with([
                    'hasManyOrder' => function ($query) {
                        $query->select('order_sn','price');
                    }
                ]);

            $search = \YunShop::request()->search;
            if ($search) {
                $records = $records->search($search)->searchMember($search);
            }
            $pageList = $records->orderBy('id', 'desc')->paginate();
            $amount = $records->sum('amount');
            $shopSet = Setting::get('shop.member');
            $pageList->map(function ($item) {
                $item->member->nickname = $item->member->nickname ?:
                    ($item->member->mobile ? substr($item->member->mobile, 0, 2) . '******' . substr($item->member->mobile, -2, 2) : '无昵称会员');
            });
            $pageList = $pageList->toArray();
            foreach ($pageList['data'] as &$item) {
                if (!$item['member']) {
                    $item['member'] = [
                        'nickname' => '已注销或已删除会员',
                        'avatar' => tomedia($shopSet['headimg']),
                    ];
                }
            }
            return $this->successJson('ok', [
                'pageList' => $pageList,
                'search' => $search,
                'income_type_comment' => $this->getIncomeTypeComment(),
                'amount' => $amount
            ]);
        }
        return view('income.income_records')->render();

    }

    private function getIncomeTypeComment()
    {
        return \app\backend\modules\income\Income::current()->getItems();
    }


    //收入金额修改
    public function changeAmount()
    {
        $id = request()->id;
        $after_amount = request()->amount;
        $incomeModel = Income::uniacid()->find($id);
        $before_amount = $incomeModel->amount;

        try {
            DB::beginTransaction();
            $incomeModel->amount = $after_amount;
            if($incomeModel->save()) {
                $log_data = [
                    'uniacid' => \YunShop::app()->uniacid,
                    'income_id' => $incomeModel->id,
                    'before' => $before_amount,
                    'after' => $after_amount,
                    'operator_id' => \YunShop::app()->uid,
                    'ip' => request()->ip()
                ];
                ChangeAmountLog::create($log_data);
                DB::commit();
                return $this->successJson('修改成功');
            }
            DB::rollBack();
            return $this->errorJson('修改失败');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorJson('修改失败', $e->getMessage());
        }
    }

    //金额修改记录
    public function changeAmountLog()
    {
        $id = request()->id;
        $list = ChangeAmountLog::uniacid()->with('User')->where('income_id', $id)->orderBy('id', 'asc')->get();

        return $this->successJson('ok', $list);

    }


}
