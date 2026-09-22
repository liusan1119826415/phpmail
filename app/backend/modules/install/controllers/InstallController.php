<?php

namespace app\backend\modules\install\controllers;


use app\backend\modules\goods\models\ReturnAddress;
use app\common\components\BaseController;

use app\common\exceptions\ShopException;
use app\common\models\Address;
use app\common\models\Order;
use app\common\models\OrderAddress;
use app\common\models\OrderGoods;
use app\common\models\project\InstallOrder;
use app\common\models\project\LogisticsTracks;
use app\common\models\project\OrderPackageVolume;
use Illuminate\Http\Request;
use app\backend\modules\install\services\InstallService;
use Yunshop\Supplier\common\models\Supplier;
use Yunshop\Supplier\common\models\SupplierInstallPrice;
use Yunshop\Supplier\common\models\SupplierLogisticPrice;
use Yunshop\Supplier\common\models\SupplierOrderReads;

class InstallController extends BaseController
{
    private InstallService $installService;


    public function __construct(InstallService $installService)
    {
        $this->installService = $installService;
    }

    public function index()
    {

        return view('install.install.list')->render();
    }


    public function getData(Request $request)
    {
        $search = $request->search;
        $data = $this->installService->getList($search);
        return $this->successJson('获取成功', $data);

    }


    /**
     * 发布订单
     */
    public function publishOrder(Request $request)
    {
        $id = $request->input('id');
        $rules = [
            'floor' => 'required|integer|',
            'install_days' => 'required|integer',
            'has_elevator' => 'required|integer|min:0',
            'remark'=>'required|string',
        ];

        $messages = [
            'floor.required' => '请输入多少楼层',
            'install_days.required' => '请输入安装日期',
            'has_elevator.required' => '请选择是否有楼层',
            'remark.required' => '请输入备注',
        ];

        $this->validate($rules, $request, $messages);
        $data = $request->validate($rules);
        $this->installService->publishOrder($id,$data);
        return $this->successJson('ok');
    }


    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \app\common\exceptions\AppException
     * 获取楼层商品清单
     */

    public function getFloorGoods(Request $request)
    {
      $id = $request->input('id');
      $data = $this->installService->getGoods($id);
      return $this->successJson('ok',$data);
    }






    public function getVoucher(Request $request)
    {

        $order_id = $request->input('id');
        $orderIds = Order::where('parent_id', $order_id)->pluck('id', 'supp_id')->toArray();
        $list = OrderPackageVolume::whereIn('order_id', $orderIds)->get();
        $orderIdMap = array_flip($orderIds); // 方便通过 order_id 找 supp_id
        $suppIds = array_keys($orderIds);
        $returnAddresses = ReturnAddress::whereIn('supplier_id', $suppIds)->where('is_refund', 2)->where('is_default', 1)->get()->keyBy('supplier_id');
        if ($list) {
            foreach ($list as $item) {
                $item->delivery_voucher = $item->delivery_voucher ? yz_tomedia($item->delivery_voucher) : "";
                $item->sign_voucher = $item->sign_voucher ? yz_tomedia($item->sign_voucher) : "";
                $suppId = $orderIdMap[$item->order_id] ?? null;

                $pickAddress = $suppId && isset($returnAddresses[$suppId])
                    ? $returnAddresses[$suppId]
                    : '';
                $item->delivery_point = $pickAddress->address_name;
            }
        }

        return $this->successJson('ok', $list);


    }


    /**
     * 获取报价
     */
    public function getQuote(Request $request)
    {

        $id = $request->input('id');
        $sort = $request->input('sort', 'asc');
        $query = SupplierInstallPrice::select("id", "store_name", "supplier_id", "order_id", "freight_price", "bidding_status","lock_status")->where('order_id', $id);
        $list = $query->orderBy('freight_price', $sort)->get();
        $data = [
            "list" => $list,
            'total_num' => $list->count()
        ];

        return $this->successJson('ok', $data);
    }


    /**
     * 分配
     */
    public function dispense(Request $request)
    {
        try {
            $id = $request->input('id');
            $result = SupplierInstallPrice::find($id);
            if (!$result) {
                throw new ShopException('未找到数据');
            }


            $fenpei = SupplierInstallPrice::where('bidding_status',4)->where('order_id',$result->order_id)->first();
            if($fenpei){
                $this->revoke($fenpei->id);
            }
            $result->bidding_status = 4;
            $result->save();

            $order = Order::find($result->order_id);
            $order->install_price = $result->freight_price;
            $order->install_dispense_status = 4;
            $order->save();

            $installOrder = InstallOrder::where('order_id',$result->order_id)->first();
            $installOrder->install_dispense_time = time();
            $installOrder->install_bidding_time = time();
            $installOrder->save();
            //发布通知
            $existsRead = SupplierOrderReads::where('supplier_id', $result->supplier_id)
                ->where('order_id', $result->order_id)->where('type',2)
                ->exists();
            if(!$existsRead){
                SupplierOrderReads::create(
                    [
                        'supplier_id' => $result->supplier_id,
                        'order_id' => $result->order_id,
                        'type' => 2,
                    ]
                );
            }
            //其他物流公司更改为未中标
            SupplierInstallPrice::where('order_id',$result->order_id)->where('id','!=',$id)->update(
              [
                  'bidding_status'=>3,
                  'notbid_time'=>time()
              ]
            );
            return $this->successJson('ok');
        } catch (\Exception $e) {
            throw new ShopException($e->getMessage());
        }

    }


    /**
     * 撤回安装分配
     */
    public function revokeDispense(Request $request)
    {
        try {
            $id = $request->input('id');

            $this->revoke($id);
            return $this->successJson('撤回成功');
        } catch (\Exception $e) {
            throw new ShopException($e->getMessage());
        }
    }

    protected function revoke($id)
    {
        $result = SupplierInstallPrice::find($id);
        if (!$result) {
            throw new ShopException('未找到数据');
        }

        // 判断当前是否为中标状态
        if ($result->bidding_status != 4) {
            throw new ShopException('该安装运输未处于已分配状态，无法撤回');
        }

        // 撤回该物流的中标状态

        $result->bidding_status = 2; // 比如改为“待审核”或“待竞标”
        $result->bided_time = 0;
        $result->save();

        $order = Order::find($result->order_id);
        $order->install_price = 0;
        $order->install_dispense_status = 3;
        $order->save();

        $installOrder = InstallOrder::where('order_id',$result->order_id)->first();
        $installOrder->install_dispense_time = 0;
        $installOrder->install_bidding_time = 0;
        $installOrder->save();

        // 删除通知（可根据业务改为修改为“已撤回”状态）
        SupplierOrderReads::where('supplier_id', $result->supplier_id)
            ->where('order_id', $result->order_id)
            ->where('type', 2)
            ->delete();

        // 将其他物流公司恢复为可竞标状态（不强制恢复，视业务而定）
        SupplierInstallPrice::where('order_id', $result->order_id)
            ->where('id', '!=', $id)
            ->where('bidding_status', 3) // 原来是“未中标”的才恢复
            ->update([
                'bidding_status' => 2, // 恢复为“待审核”或“待竞标”
                'notbid_time' => null
            ]);

    }


    public function lockPrice(Request $request)
    {
        $id = $request->input('id');
        $result = SupplierInstallPrice::find($id);
        if (!$result) {
            throw new ShopException('未找到数据');
        }
        if($result->bidding_status == 4){
            throw new ShopException('订单已经尾款支付无法撤销锁定');
        }
        $result->lock_status = $result->lock_status == 0?1:0;
        $result->save();
        SupplierInstallPrice::where('order_id',$result->order_id)->where('id','!=',$id)->update(
            [
                'lock_status'=>0
            ]
        );
        return $this->successJson('操作成功');
    }


    public function deliveryGoods(Request $request)
    {
        $this->validate([
            'id' => 'required|integer|min:0',
        ]);
        $order_id = $request->input('id');
        $order = Order::find($order_id);
        if (!$order) {
            throw new ShopException('订单不存在');
        }
        $returnAddresses = ReturnAddress::where('supplier_id', $order->supp_id)->where('is_refund', 2)->where('is_default', 1)->first();

        $orderGoods = OrderGoods::where('order_id', $order_id)
            ->with(['goodsOption', 'hasOneGoods'])
            ->get();
        $total_volume = 0;
        $total_package = 0;
        $result = [];
        foreach ($orderGoods as $item) {

            $total_volume += $item->goodsOption->volume ?? 0;
            $total_package += $item->goodsOption->package_number ?? 0;
            $result[] = [
                "thumb" => yz_tomedia($item->thumb),
                "title" => $item->title,
                "product_model" => $item->goodsOption->product_model,
                "specs" => $item->goods_option_title,
                "package_number" => $item->goodsOption->package_number ?? 0,
                "volume" => $item->goodsOption->volume ?? 0
            ];

        }

        $data = [
            "delivery_point" => $returnAddresses->address_name,
            "delivery_address" => implode(' ', array_filter([
                $returnAddresses->province_name,
                $returnAddresses->city_name,
                $returnAddresses->district_name,
                $returnAddresses->street_name,
                $returnAddresses->address
            ])),
            "total_volume" => $total_volume,
            "total_package" => $total_package,
            "product_list" => $result
        ];
        return $this->successJson('ok', $data);

    }


    public function detail(Request $request)
    {
        $order_id = $request->input('id');
        if (request()->ajax()) {
            $order = \app\backend\modules\order\models\VueOrder::with(['belongsToMember' => function ($query) {
                $query->select("uid", 'nickname', "mobile", "avatar");
            },'installOrder','supplierInstallPrice'])->find($order_id);


            $orderIds = Order::where('parent_id', $order_id)->pluck('id')->toArray();

            $goods_total = Order::whereIn('id',$orderIds)->sum('goods_total');
            $receiv_address = OrderAddress::where('order_main_id', $order_id)->first();


            // 2. 查询订单商品 + goodsOption
            $filtered = $order->supplierInstallPrice->where('bidding_status', 4)->first();
            $supplier = Supplier::where('id',$filtered->supplier_id)->first();
            if($supplier){
                $company_name = $supplier->store_name;
                $mobile = $supplier->mobile;
                $addressMap = Address::whereIn('id',[$supplier->province_id,$supplier->city_id,$supplier->district_id,$supplier->street_id])->pluck('areaname')->toArray();

            }
            $data = [
                "order_sn" => $order->order_sn,
                "backend_button_models" => $order->backend_button_models,
                "order_status_data" => [
                    "dispense_status" => $order->install_dispense_status,
                    "create_time" => $order->create_time->format('Y-m-d H:i:s'),
                    "install_dispense_time" =>$order->installOrder->install_dispense_time?date("Y-m-d H:i:s",$order->installOrder->install_dispense_time):"",
                    "install_sign_time" =>$order->installOrder->install_sign_time?date("Y-m-d H:i:s",$order->installOrder->install_sign_time):"",
                    "publish_time" => $order->installOrder->install_publish_time?date("Y-m-d H:i:s",$order->installOrder->install_publish_time):"",
                    "bid_time" => $order->installOrder->install_bidding_time?date("Y-m-d H:i:s",$order->installOrder->install_bidding_time):"",
                    "quote_time" => $order->installOrder->install_quote_time?date("Y-m-d H:i:s",$order->installOrder->install_quote_time):"",
                ],
                'member' => $order->belongsToMember,
                "installOrder"=>$order->installOrder,
                "install_price" => $order->install_price,
                "receiv_address" => $receiv_address->address,
                "receiv_name" => $receiv_address->realname,
                'receiv_mobile' => $receiv_address->mobile,
                "goods_total" => $goods_total,
                "company_info"=>[
                    'company_name'=>$company_name,
                    'mobile'=>$mobile,
                    'address'=>implode(' ', array_filter([
                        $addressMap[0] ?? '',
                        $addressMap[1] ?? '',
                        $addressMap[2] ?? '',
                        $addressMap[3] ?? '',
                        $supplier->address
                    ]))
                ]

            ];

            $data['min_freight_price'] = $order->supplierInstallPrice->min('freight_price');
            $data['logistic_company_num'] = $order->supplierInstallPrice->count();

            return $this->successJson('ok', $data);

        }

        return view('install.install.detail', ['id' => $order_id]);

    }


    public function getLogisticsTracks(Request $request){
        $order_id = $request->input('id');
        $data = LogisticsTracks::getTracks($order_id);
        return $this->successJson('ok',$data);
    }


}