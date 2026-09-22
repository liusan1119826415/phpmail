<?php

namespace app\backend\modules\finance\controllers;


use app\backend\modules\goods\models\ReturnAddress;
use app\common\components\BaseController;

use app\common\exceptions\ShopException;
use app\common\models\Address;
use app\common\models\Order;
use app\common\models\OrderAddress;
use app\common\models\OrderGoods;
use app\common\models\project\InstallOrder;
use app\common\models\project\OrderPackageVolume;
use app\common\services\Session;
use Illuminate\Http\Request;
use app\backend\modules\finance\services\TradeService;
use Yunshop\Supplier\common\models\Supplier;
use Yunshop\Supplier\common\models\SupplierLogisticPrice;
use Yunshop\Supplier\common\models\SupplierOrderReads;

class TradeController extends BaseController
{
    private TradeService $tradeService;


    public function __construct(TradeService $tradeService)
    {
        $this->tradeService = $tradeService;
    }

    public function index()
    {

        return view('finance.trade.list')->render();
    }


    public function getData(Request $request)
    {
        $search = $request->search;
        $data = $this->tradeService->getList($search);
        return $this->successJson('获取成功', $data);

    }



    public function detail(Request $request)
    {
        $project_id = $request->input('id');
        if (request()->ajax()) {

            $data = $this->tradeService->detail($project_id);
            return $this->successJson('ok', $data);

        }

        return view('finance.trade.detail', ['id' => $project_id]);

    }


    public function getPayList(Request $request)
    {
        $project_id = $request->input('project_id');
        $pay_type = $request->input('pay_type');
        if (request()->ajax()) {

            $data = $this->tradeService->getPayList($project_id,$pay_type);
            return $this->successJson('ok', $data);

        }

    }

    public function getOrderDetail(Request $request)
    {
        $project_id = $request->input('project_id');
        if (request()->ajax()) {

            $data = $this->tradeService->getOrderDetail($project_id);
            return $this->successJson('ok', $data);

        }
    }

    public function getPayVoucher(Request $request)
    {
        $pay_id = $request->input('id');
        if (request()->ajax()) {

            $data = $this->tradeService->getPayVoucher($pay_id);
            return $this->successJson('ok', $data);

        }
    }

    public function getIncomeData(Request $request)
    {
        $project_id = $request->input('project_id');
        if (request()->ajax()) {

            $data = $this->tradeService->getIncomeData($project_id);
            return $this->successJson('ok', $data);

        }
    }

    public function getRefundData(Request $request)
    {
        $project_id = $request->input('project_id');
        if (request()->ajax()) {

            $data = $this->tradeService->getRefundData($project_id);
            return $this->successJson('ok', $data);

        }
    }

    public function getDiscountData(Request $request)
    {
        $project_id = $request->input('project_id');
        if (request()->ajax()) {

            $data = $this->tradeService->getDiscountData($project_id);
            return $this->successJson('ok', $data);

        }
    }















}