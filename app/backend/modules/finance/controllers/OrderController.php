<?php

namespace app\backend\modules\finance\controllers;


use app\backend\modules\finance\services\CommonService;
use app\backend\modules\goods\models\ReturnAddress;
use app\backend\modules\order\models\OrderPay;
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
use app\backend\modules\finance\services\OrderService;
use Yunshop\Supplier\common\models\Supplier;
use Yunshop\Supplier\common\models\SupplierInstallPrice;
use Yunshop\Supplier\common\models\SupplierLogisticPrice;
use Yunshop\Supplier\common\models\SupplierOrderReads;

class OrderController extends BaseController
{
    private OrderService $orderService;


    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function index()
    {

        return view('finance.order.list')->render();
    }

    public function test()
    {
        $callback = ($_SERVER['REQUEST_SCHEME'] ? $_SERVER['REQUEST_SCHEME'] : 'http')  . '://' . $_SERVER['HTTP_HOST']."/addons/yun_shop/api.php?i=1&type=5&route=project.member.handleCallback";
        print_r($callback);die;
    }



    public function getData(Request $request)
    {
        $search = $request->search;
        $data = $this->orderService->getList($search);
        return $this->successJson('获取成功', $data);

    }


    /**
     * 发布订单
     */
    public function getCredentials(Request $request)
    {
        $id = $request->input('id');
        $data = $this->orderService->getCredentials($id);
        return $this->successJson('ok', $data);
    }


    public function detail(Request $request)
    {
        $order_id = $request->input('id');
        if (request()->ajax()) {

            $data = $this->orderService->detail($order_id);
            return $this->successJson('ok', $data);

        }

        return view('finance.order.detail', ['id' => $order_id]);

    }

    public function confirmPay(Request $request)
    {
        $order_id = $request->input('id');

        $this->orderService->confirmPay($order_id);
        return $this->successJson('操作成功');
    }

    public function getPayVoucher(Request $request)
    {
        $id = $request->input('id');

        $data = $this->orderService->getPayVoucher($id);
        return $this->successJson('操作成功',$data);
    }
    public function revokePay(Request $request)
    {
        $id = $request->input('id');

        $this->orderService->revokePay($id);
        return $this->successJson('操作成功');
    }

    public function getVoucher(Request $request)
    {
        $order_id = $request->input('id');
        $data = $this->orderService->getVoucher($order_id);
        return $this->successJson('ok', $data);
    }

    public function viewVoucher(Request $request)
    {
        $order_id = $request->input('order_id');
        $data = $this->orderService->viewVoucher($order_id);
        return $this->successJson('ok',$data);
    }

    public function confirmLogisticPay(Request $request)
    {
        $order_id = $request->input('id');
        $supplier_id = $request->input('supplier_id');
        $this->orderService->confirmLogisticPay($order_id, $supplier_id);
        return $this->successJson('操作成功');
    }

    public function revokeLogisticPay(Request $request)
    {
        $order_id = $request->input('id');
        $supplier_id = $request->input('supplier_id');
        $this->orderService->revokeLogisticPay($order_id, $supplier_id);
        return $this->successJson('操作成功');
    }

    public function getLogisticVoucher(Request $request)
    {
        $order_id = $request->input('id');
        $supplier_id = $request->input('supplier_id');
        $order = \app\backend\modules\order\models\Order::find($order_id);

        $orderPay = OrderPay::where('order_id',$order_id)->where('income_type',CommonService::INCOME_TYPE_LOGISTIC)->where('pay_form',CommonService::PLAT_TO_BRAND)
            ->where('income',CommonService::PAY_COME)->where('supplier_id',$supplier_id)
            ->where('payment_stage',1)->first();
        $data = [
            "order_id" => $order_id,
            "supplier_id" => $supplier_id,
            "logistic_status" => $order->logistics_status,
            "freight_price"=>$order->freight_price,
             "swift_img"=>yz_tomedia($orderPay->thumb)
        ];
        return $this->successJson('ok', $data);

    }

    public function confirmInstallPay(Request $request)
    {
        $order_id = $request->input('id');
        $this->orderService->confirmInstallPay($order_id);
        return $this->successJson('操作成功');
    }


    public function revokeInstallPay(Request $request)
    {
        $order_id = $request->input('id');
        $this->orderService->revokeInstallPay($order_id);
        return $this->successJson('操作成功');
    }


    public function getInstallVoucher(Request $request)
    {
        $order_id = $request->input('id');

        $order = \app\backend\modules\order\models\Order::find($order_id);
        $installOrder = InstallOrder::where('order_id', $order_id)->first();
        $supplierInstall = SupplierInstallPrice::where('order_id', $order_id)->where('lock_status', 1)->first();
        $orderPay = OrderPay::where('order_id',$order_id)->where('income_type',CommonService::INCOME_TYPE_INSTALL)->where('pay_form',CommonService::PLAT_TO_BRAND)
            ->where('income',CommonService::PAY_COME)->where('supplier_id',$supplierInstall->supplier_id)
            ->where('payment_stage',1)->first();
        $data = [
            "order_id" => $order_id,
            "install_status" => $installOrder->install_status,
            "install_price"=>$order->install_price,
            "swift_img"=>yz_tomedia($orderPay->thumb)
        ];
        return $this->successJson('ok', $data);

    }

    public function getSignVoucher(Request $request)
    {
        $order_id = $request->input('id');

        $data = $this->orderService->getSignVoucher($order_id);
        return $this->successJson('ok', $data);

    }








}