<?php

namespace app\frontend\modules\project\controllers;

use app\common\components\ApiController;
use app\frontend\modules\project\services\BidOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class BidOrderController extends ApiController
{


    public $transactionActions =["applyFor"];

    /**
     *创建我的项目
     */

    private BidOrderService $bidOrderService;

    public function __construct(BidOrderService $bidOrderService)
    {
        $this->bidOrderService = $bidOrderService;
        parent::__construct();
    }




    public function getList(Request $request)
    {

        $search = $request->input("search",[]);
        $data = $this->bidOrderService->getList($search);
        return $this->successJson('ok',$data);
    }


    public function getApplyRefund(Request $request)
    {
        $validat = [
            'id' => 'required|integer|min:0',
        ];
        $this->validate($validat);
        $id = $request->input("id");
        $data = $this->bidOrderService->getApplyRefund($id);
        return $this->successJson('ok',$data);
    }

    public function getDetail(Request $request)
    {


        $validat = [
            'id' => 'required|integer|min:0',
        ];
        $this->validate($validat);
        $id = $request->input("id");
        $data = $this->bidOrderService->getDetail($id);
        return $this->successJson('ok',$data);
    }

    public function confirmSign(Request $request)
    {
        $validat = [
            'order_id' => 'required|integer|min:0',
        ];
        $this->validate($validat);
        $order_id = $request->input("order_id");
        $data = $this->bidOrderService->confirmSign($order_id);
        return $this->successJson('ok',$data);


    }

    public function getLogistic(Request $request)
    {
        $validat = [
            'order_id' => 'required|integer|min:0',
        ];
        $this->validate($validat);
        $order_id = $request->input("order_id");
        $data = $this->bidOrderService->getLogistic($order_id);
        return $this->successJson('ok',$data);


    }

    public function getAfterSales(Request $request)
    {
        $search = $request->input("search",[]);
        $data = $this->bidOrderService->getAfterSales($search);
        return $this->successJson('ok',$data);

    }







}