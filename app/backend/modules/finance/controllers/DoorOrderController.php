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
use app\backend\modules\finance\services\DoorOrderService;
use Yunshop\Supplier\common\models\Supplier;
use Yunshop\Supplier\common\models\SupplierLogisticPrice;
use Yunshop\Supplier\common\models\SupplierOrderReads;

class DoorOrderController extends BaseController
{
    private DoorOrderService $doorOrderService;


    public function __construct(DoorOrderService $doorOrderService)
    {
        $this->doorOrderService = $doorOrderService;
    }

    public function index()
    {

        return view('finance.doororder.list')->render();
    }


    public function getData(Request $request)
    {
        $search = $request->search;
        $data = $this->doorOrderService->getList($search);
        return $this->successJson('获取成功', $data);

    }



    public function detail(Request $request)
    {
        $order_id = $request->input('id');
        if (request()->ajax()) {
            $data = $this->doorOrderService->detail($order_id);
            return $this->successJson('ok', $data);
        }

        return view('finance.doororder.detail', ['id' => $order_id]);

    }


    public function confirmPay(Request $request)
    {
        $order_id = $request->input('id');
        $swift_img = $request->input('swift_img');
        $order = Order::find($order_id);
        if(!in_array($order->status,[1,2,3])){
            throw new ShopException("订单还待支付状态");
        }
        $this->doorOrderService->confirmPay($order_id,$swift_img);
        return $this->successJson('ok');
    }


    public function revokePay(Request $request)
    {
        $order_id = $request->input('id');
        $this->doorOrderService->revokePay($order_id);
        return $this->successJson('ok');
    }

    public function getPayVoucher(Request $request)
    {
        $order_id = $request->input('id');
        $data = $this->doorOrderService->getPayVoucher($order_id);
        return $this->successJson('ok',$data);
    }









}