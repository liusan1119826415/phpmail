<?php

namespace app\frontend\modules\project\controllers;

use app\common\components\ApiController;
use app\common\exceptions\AppException;
use app\frontend\models\Order;
use app\frontend\modules\order\services\behavior\OrderPay;
use app\frontend\modules\project\services\PreOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class OrderController extends ApiController
{




    /**
     *创建我的项目
     */

    private PreOrderService $preOrderService;

    public function __construct(PreOrderService $preOrderService)
    {
        $this->preOrderService = $preOrderService;
        parent::__construct();
    }




    public function preOrder(Request $request)
    {
        $this->validate([
            'project_id'=>'required|integer|min:0',
        ]);
        $project_id = $request->input("project_id",0);

        $data = $this->preOrderService->preOrder($project_id);
        return $this->successJson('ok',$data);
    }


    public function createOrder(Request $request)
    {
        $this->validate([
            'project_id'=>'required|integer|min:0',
        ]);
        $project_id = $request->input("project_id",0);
        $this->PreventDuplicateSubmission($request);
        $data = $this->preOrderService->createOrder($project_id);

        return $this->successJson('ok',$data);
    }


    public function getList(Request $request)
    {
        $search = $request->input("search",[]);
        $data = $this->preOrderService->getList($search);
        return $this->successJson('ok',$data);
    }


    public function getRecycleOrder(Request $request)
    {
        $search = $request->input("search",[]);
        $data = $this->preOrderService->getRecycleOrder($search);
        return $this->successJson('ok',$data);
    }





    public function detail(Request $request)
    {

        $this->validate([
            'id'=>'required|integer|min:0',
        ]);
        $id = $request->input("id");
        $data = $this->preOrderService->detail($id);
        return $this->successJson('ok',$data);

    }


    public function delete(Request $request)
    {

        $this->validate([
            'id'=>'required|integer|min:0',
        ]);
        $id = $request->input("id");
        $data = $this->preOrderService->delete($id);
        return $this->successJson('ok');

    }


    public function recycle(Request $request)
    {

        $this->validate([
            'id'=>'required|integer|min:0',
        ]);
        $id = $request->input("id");
        $data = $this->preOrderService->recycle($id);
        return $this->successJson('ok');

    }

    public function getDrawList(Request $request)
    {
        $search = $request->input("search",[]);
        $data = $this->preOrderService->getDrawList($search);
        return $this->successJson('ok',$data);
    }

    public function batchConfirm(Request $request)
    {
        $ids = $request->input('ids',[]);
        $this->PreventDuplicateSubmission($request);
        $this->preOrderService->batchConfirm($ids);
        return $this->successJson('ok');
    }

    public function getOrderGoods(Request $request)
    {
//        $this->validate([
//            'order_id'=>'required|integer|min:0',
//        ]);
        $search = $request->input("search",[]);
        $data = $this->preOrderService->getOrderGoods($search);
        return $this->successJson('ok',$data);

    }

    public function getProductSchedule(Request $request)
    {
        /*$this->validate([
            'project_id'=>'required|integer|min:0',
        ]);*/
        $project_id = $request->input("project_id",0);
        $data = $this->preOrderService->getProductSchedule($project_id);
        return $this->successJson('ok',$data);
    }


    public function getAfterSalesOrder(Request $request)
    {
        $search = $request->input("search",[]);
        $data = $this->preOrderService->getAfterSalesOrder($search);
        return $this->successJson('ok',$data);
    }

    public function getAfterSalesOrderDetail(Request $request)
    {
        $this->validate([
            'order_id'=>'required|integer|min:0',
        ]);

        $order_id = $request->input("order_id",0);
        $data = $this->preOrderService->getAfterSalesOrderDetail($order_id);
        return $this->successJson('ok',$data);

    }

    public function getLogisticsTrack(Request $request)
    {
        $this->validate([
            'order_id'=>'required|integer|min:0',
        ]);

        $order_id = $request->input("order_id",0);
        $data = $this->preOrderService->getLogisticsTrack($order_id);
        return $this->successJson('ok',$data);

    }

    public function getInstallTrack(Request $request)
    {
        $this->validate([
            'order_id'=>'required|integer|min:0',
        ]);

        $order_id = $request->input("order_id",0);
        $data = $this->preOrderService->getInstallTrack($order_id);
        return $this->successJson('ok',$data);

    }

    public function getRemittanceResult(Request $request)
    {
        $this->validate([
            'order_pay_id'=>'required|integer|min:0',
        ]);

        $order_pay_id= $request->input("order_pay_id",0);
        $data = $this->preOrderService->getRemittanceResult($order_pay_id);
        return $this->successJson('ok',$data);
    }

    public function confirmSign(Request $request)
    {
        $this->validate([
            'order_id'=>'required|integer|min:0',
        ]);

        $order_id= $request->input("order_id",0);
        $data = $this->preOrderService->confirmSign($order_id);
        return $this->successJson('ok');
    }


    public function cancelConfirm(Request $request)
    {
        $this->validate([
            'order_goods_id'=>'required|integer|min:0',
        ]);

        $order_goods_id= $request->input("order_goods_id",0);
        $this->preOrderService->cancelConfirm($order_goods_id);
        return $this->successJson('ok');
    }

    public function getAddress()
    {
        $data = $this->preOrderService->getAddress();
        return $this->successJson('ok',$data);
    }

    public function submitOrderSuccess(Request $request)
    {
        $this->validate([
            'order_id'=>'required|integer|min:0',
        ]);
        $order_id= $request->input("order_id",0);
        $data = $this->preOrderService->submitOrderSuccess($order_id);
        return $this->successJson('ok',$data);

    }

    public function setExtraAmount(Request $request)
    {
        $this->validate([
            'order_id'=>'required|integer|min:0',
            'expense_type'=>'required|integer|min:1',
            'extra_amount'=>'required|numeric',
        ]);
        $order_id= $request->input("order_id",0);
        $expense_type = $request->input("expense_type",1);
        $extra_amount = $request->input("extra_amount");

        $order = Order::find($order_id);
        if(!$order){
            throw new AppException('未找到订单');
        }

        $order->expense_type = $expense_type;
        $order->extra_amount = $extra_amount;
        $order->save();
        return $this->successJson('ok');

    }

    public function changePrice(Request $request)
    {
        $this->validate([
            'order_id'=>'required|integer|min:0',
            'amount'=>'required|numeric',
        ]);
        $order_id= $request->input("order_id",0);
        $amount = $request->input("amount");

        $order = Order::find($order_id);
        if(!$order){
            throw new AppException('未找到订单');
        }
        $order->price = $amount;
        $order->goods_price = $amount;
        $order->save();
        return $this->successJson('ok');

    }

    public function getPickupPoint(Request $request)
    {
        $this->validate([
            'order_id'=>'required|integer|min:0',
        ]);
        $order_id= $request->input("order_id",0);
        $data = $this->preOrderService->getPickupPoint($order_id);

        return $this->successJson('ok',$data);

    }

    public function deliveryBill(Request $request)
    {
        $this->validate([
            'order_id'=>'required|integer|min:0',
        ]);
        $order_id= $request->input("order_id",0);
        $data = $this->preOrderService->deliveryBill($order_id);

        return $this->successJson('ok',$data);

    }

    public function verifyBeforeOrder(Request $request)
    {
        $this->validate([
            'project_id'=>'required|integer|min:0',
        ]);
        $project_id= $request->input("project_id",0);
        $result = $this->preOrderService->verifyBeforeOrder($project_id);

        if($result['status'] == 0){
            $data['offShelf'] = $result['offShelf'];
            $data['offShelfGoods'] = $result['offShelfGoods'];
            return $this->errorJson($result['msg'],$data);
        }
        return $this->successJson('ok');
    }


    public function getPayVoucher(Request $request)
    {
        $this->validate([
            'pay_id'=>'required|integer|min:0',
        ]);
        $pay_id = $request->input("pay_id",0);
        $data = \app\frontend\models\OrderPay::with(['order'=>function($q){
            $q->select("id","order_sn",'status');
        }])->find($pay_id);
        $data->thumb = yz_tomedia($data->thumb);

        return $this->successJson('ok',$data);
    }

















}