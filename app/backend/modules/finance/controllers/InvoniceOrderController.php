<?php

namespace app\backend\modules\finance\controllers;


use app\backend\modules\finance\models\OrderInvonice;
use app\backend\modules\goods\models\ReturnAddress;
use app\common\components\BaseController;

use app\common\exceptions\ShopException;
use app\common\models\Address;
use app\common\models\Order;
use app\common\models\OrderAddress;
use app\common\models\OrderGoods;
use app\common\models\project\OrderInvoiceUploads;
use app\common\models\project\OrderPackageVolume;
use app\common\services\Session;
use Illuminate\Http\Request;
use app\backend\modules\finance\services\InvoniceOrderService;
use Yunshop\Supplier\common\models\Supplier;
use Yunshop\Supplier\common\models\SupplierLogisticPrice;
use Yunshop\Supplier\common\models\SupplierOrderReads;
use Illuminate\Support\Facades\DB;
class InvoniceOrderController extends BaseController
{
    private InvoniceOrderService $invoniceOrderService;


    public function __construct(InvoniceOrderService $invoniceOrderService)
    {
        $this->invoniceOrderService = $invoniceOrderService;
    }

    public function index()
    {

        return view('finance.invonice_order.list')->render();
    }


    public function getData(Request $request)
    {
        $search = $request->search;
        $data = $this->invoniceOrderService->getList($search);
        return $this->successJson('获取成功', $data);

    }


    /**
     * 获取发票订单
     */
    public function getOrderInvoiceInfo(Request $request)
    {
        $id = $request->input('id');
        $data = $this->invoniceOrderService->getOrderInvoiceInfo($id);
        return $this->successJson('ok',$data);
    }

    public function uploadInvonice(Request $request)
    {

        $validated = $request->validate([
            'invonice_id'=>'required|integer',
            'uploads' => 'required|array|min:1',
            'uploads.*.id' => 'required|integer|exists:yz_order_invoice_uploads,id',
            'uploads.*.invoice_file' => 'required|string|max:1000',
        ]);
        DB::transaction(function () use ($validated) {
            foreach ($validated['uploads'] as $uploadData) {
                OrderInvoiceUploads::where('id', $uploadData['id'])
                    ->update([
                        'invoice_file' => $uploadData['invoice_file'],
                    ]);
            }
            OrderInvonice::where('id',$validated['invonice_id'])->update(
                [
                    'status'=>2,
                    'open_time'=>time()
                ]
            );
        });
        return $this->successJson('操作成功');
    }

    public function detail(Request $request)
    {
        $id = $request->input('id');
        if (request()->ajax()) {

            $data = $this->invoniceOrderService->detail($id);
            return $this->successJson('ok', $data);

        }
        return view('finance.invonice_order.detail',['id'=>$id])->render();
    }








}