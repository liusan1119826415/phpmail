<?php

namespace app\frontend\modules\project\controllers;

use app\common\components\ApiController;
use app\frontend\modules\project\services\OrderInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class OrderInvoiceController extends ApiController
{

   public $transactionActions = ["apply"];

    /**
     *创建我的项目
     */

    private OrderInvoiceService $orderInvoiceService;

    public function __construct(OrderInvoiceService $orderInvoiceService)
    {
        $this->orderInvoiceService = $orderInvoiceService;
        parent::__construct();
    }




    public function storeTitle(Request $request)
    {
        $validat = [
            'title_name' => 'required|string',
            'is_default'=>'required|integer|min:0',
            'title_type'=>'required|integer|min:0',
        ];
        $this->validate($validat,$request,[
            'title_name.required' => '抬头名称必须',
            'title_type.required' => '抬头类型必须',
        ]);
        $data = $request->validate($validat);
        $this->orderInvoiceService->storeTitle($data);
        return $this->successJson('ok');
    }


    public function updateTitle(Request $request)
    {
        $validat = [
            'id' => 'required|integer|min:0',
            'title_name' => 'required|string',
        ];
        $this->validate($validat);
        $id = $request->input("id");
        $title_name = $request->input("title_name");
        $this->orderInvoiceService->updateTitle($id,$title_name);
        return $this->successJson('ok');
    }

    public function destroyTitle(Request $request)
    {
        $validat = [
            'id' => 'required|integer|min:0',
        ];
        $this->validate($validat);
        $id = $request->input("id");
        $this->orderInvoiceService->destroyTitle($id);
        return $this->successJson('ok');
    }

    public function setDefault(Request $request)
    {
        $validat = [
            'id' => 'required|integer|min:0',
        ];
        $this->validate($validat);
        $id = $request->input("id");
        $this->orderInvoiceService->setDefault($id);
        return $this->successJson('ok');
    }

    public function getTitleList(Request $request)
    {
        $order_id = $request->input('order_id',0);
        $data = $this->orderInvoiceService->getTitleList($order_id);
        return $this->successJson('ok',$data);
    }

    public function apply(Request $request)
    {
        $rules = [
            'order_id'          => 'required|integer|min:0',
            'invoice_title_id'  => 'nullable|integer|required_without:title_name',
            'title_name'        => 'nullable|string|required_without:invoice_title_id',
            'invoice_type'      => 'required|integer|min:0',
            'company_id'        => 'required_if:invoice_type,2|integer',
        ];

        $messages = [
            'order_id.required'             => '订单ID必须',
            'invoice_type.required'         => '发票类型必须',
            'invoice_title_id.required_without' => '发票抬头ID或抬头名称必须填写一个',
            'title_name.required_without'   => '抬头名称或发票抬头ID必须填写一个',
            'company_id.required_if'        => '企业类发票请填写企业ID',
        ];
        $this->validate($rules,$request,$messages);
        $data = $request->validate($rules);
        $this->orderInvoiceService->apply($data);
        return $this->successJson('ok');
    }

    public function updateApply(Request $request)
    {
        $rules = [

            'invoice_title_id'  => 'nullable|integer|required_without:title_name',
            'title_name'        => 'nullable|string|required_without:invoice_title_id',

        ];

        $messages = [

            'invoice_title_id.required_without' => '发票抬头ID或抬头名称必须填写一个',
            'title_name.required_without'   => '抬头名称或发票抬头ID必须填写一个',

        ];
        $this->validate($rules,$request,$messages);
        $data = $request->validate($rules);
        $this->orderInvoiceService->updateApply($data);
        return $this->successJson('ok');
    }

    public function getList(Request $request)
    {
        $search = $request->input("search",[]);
        $data = $this->orderInvoiceService->getList($search);
        return $this->successJson('ok',$data);
    }

    public function getDetail(Request $request)
    {
        $validat = [
            'id' => 'required|integer|min:0',
        ];
        $this->validate($validat);
        $id = $request->input("id");
        $data = $this->orderInvoiceService->getDetail($id);
        return $this->successJson('ok',$data);
    }

    public function revoke(Request $request)
    {
        $validat = [
            'id' => 'required|integer|min:0',
        ];
        $this->validate($validat);
        $id = $request->input("id");
        $this->orderInvoiceService->revoke($id);
        return $this->successJson('ok');
    }

    public function getNotInvoice(Request $request)
    {

        $name = $request->input("name");
        $this->orderInvoiceService->getNotInvoice($name);
        return $this->successJson('ok');
    }














}