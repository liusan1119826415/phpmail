<?php

namespace app\backend\modules\finance\controllers;


use app\backend\modules\goods\models\ReturnAddress;
use app\common\components\BaseController;

use app\common\exceptions\ShopException;
use app\common\models\Address;
use app\common\models\Order;
use app\common\models\OrderAddress;
use app\common\models\OrderGoods;
use app\common\models\project\OrderPackageVolume;
use app\common\services\Session;
use Illuminate\Http\Request;
use app\backend\modules\finance\services\AfterOrderService;
use Yunshop\Supplier\common\models\Supplier;
use Yunshop\Supplier\common\models\SupplierLogisticPrice;
use Yunshop\Supplier\common\models\SupplierOrderReads;

class AfterOrderController extends BaseController
{
    private AfterOrderService $afterOrderService;


    public function __construct(AfterOrderService $afterOrderService)
    {
        $this->afterOrderService = $afterOrderService;
    }

    public function index()
    {

        return view('finance.afterorder.list')->render();
    }


    public function getData(Request $request)
    {
        $search = $request->search;
        $data = $this->logisticsService->getList($search);
        return $this->successJson('获取成功', $data);

    }


    /**
     * 发布订单
     */
    public function publishOrder(Request $request)
    {
        $id = $request->input('id');
        $this->logisticsService->publishOrder($id);
        return $this->successJson('ok');
    }


    public function deliveryPoint(Request $request)
    {

        $this->validate([
            'id' => 'required|integer|min:0',
        ]);
        $order_id = $request->input('id');
        $delivery_type = $request->input('delivery_type');
        $receiv_address = OrderAddress::where('order_main_id', $order_id)->first();
        $pick_num = OrderAddress::where('order_main_id', $order_id)->count();

        // 1. 获取子订单（supp_id => order_id）
        $orderIds = Order::where('parent_id', $order_id)->pluck('id', 'supp_id')->toArray();
        $suppIds = array_keys($orderIds);
        $orderIdMap = array_flip($orderIds); // 方便通过 order_id 找 supp_id

        // 2. 查询订单商品 + goodsOption
        $orderGoods = OrderGoods::whereIn('order_id', array_values($orderIds))
            ->with(['goodsOption', 'hasOneGoods'])
            ->get()
            ->groupBy('order_id');

        // 3. 查询提货地址
        $returnAddresses = ReturnAddress::whereIn('supplier_id', $suppIds)->where('is_refund', 2)->where('is_default', 1)->get()->keyBy('supplier_id');

        // 4. 构造最终统计结构
        $result = [];
        $total_volume = 0;
        $total_package = 0;
        foreach ($orderGoods as $orderId => $items) {
            $volume = 0;
            $package = 0;
            $max_lead_time = 0;

            foreach ($items as $item) {
                if ($item->goodsOption) {
                    $volume += $item->goodsOption->volume ?? 0;
                    $package += $item->goodsOption->package_number ?? 0;
                }

                // 获取最大的 lead_time
                if ($item->hasOneGoods && isset($item->hasOneGoods->lead_time)) {
                    $leadTime = (int)$item->hasOneGoods->lead_time;
                    if ($leadTime > $max_lead_time) {
                        $max_lead_time = $leadTime;
                    }
                }
            }

            // 获取对应订单的 pay_time
            $order = Order::find($orderId);  // 可以考虑预加载优化
            $pay_time = $order->pay_time ?? null;
            $production_end_date = $pay_time ? $pay_time->copy()->addDays($max_lead_time)->toDateString() : '';


            $suppId = $orderIdMap[$orderId] ?? null;
            $pickAddress = $suppId && isset($returnAddresses[$suppId])
                ? $returnAddresses[$suppId]
                : '';
            $total_volume += $volume;
            $total_package += $package;
            $array = [
                'volume' => $volume,
                'package_number' => $package,
                'delivery_address' => implode(' ', array_filter([
                    $pickAddress->province_name,
                    $pickAddress->city_name,
                    $pickAddress->district_name,
                    $pickAddress->street_name,
                    $pickAddress->address
                ])),
                "delivery_point" => $pickAddress->address_name,
                "order_id" => $orderId,
                //'delivery_phone'=>$pickAddress->contact."/".$pickAddress->mobile,
                'delivery_date' => $production_end_date
            ];
            if ($delivery_type == 2) {
                $array['delivery_phone'] = $pickAddress->contact . "/" . $pickAddress->mobile;
            }
            $result[] = $array;
        }

        $data = [
            "pick_num" => $pick_num,
            "receiv_address" => $receiv_address->address,
            "total_volume" => $total_volume,
            "total_package" => $total_package,
            "point_data" => $result
        ];

        return $this->successJson('ok', $data);


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
     * 获取物流报价
     */
    public function getQuote(Request $request)
    {

        $id = $request->input('id');
        $sort = $request->input('sort', 'desc');
        $query = SupplierLogisticPrice::select("id", "store_name", "supplier_id", "order_id", "freight_price", "logistics_status")->where('order_id', $id);
        $list = $query->orderBy('freight_price', $sort)->get();
        $data = [
            "list" => $list,
            'total_num' => $list->count()
        ];

        return $this->successJson('ok', $data);
    }


    /**
     * 分配物流
     */
    public function dispense(Request $request)
    {
        try {
            $id = $request->input('id');
            $result = SupplierLogisticPrice::find($id);
            if (!$result) {
                throw new ShopException('未找到数据');
            }
            $result->logistics_status = 1;
            $result->bidding_status = 4;
            $result->bid_time = time();
            $result->save();

            $order = Order::find($result->order_id);
            $order->freight_price = $result->freight_price;
            $order->bid_time = time();
            $order->logistics_status = 2;
            $order->dispense_status = 4;
            $order->save();
            //发布通知
            $existsRead = SupplierOrderReads::where('supplier_id', $result->supplier_id)
                ->where('order_id', $id)->where('type',1)
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
            SupplierLogisticPrice::where('order_id',$result->order_id)->where('id','!=',$id)->update(
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
            }, 'supplier' => function ($query) {
                $query->select("id", "realname", "mobile", "province_id", "city_id", "district_id", "street_id", "address", "store_name");
            },'supplierPrice'])->find($order_id);


            $orderIds = Order::where('parent_id', $order_id)->pluck('id', 'supp_id')->toArray();
            $suppIds = array_keys($orderIds);
            $receiv_address = OrderAddress::where('order_main_id', $order_id)->first();
            $orderIdMap = array_flip($orderIds); // 方便通过 order_id 找 supp_id

            // 2. 查询订单商品 + goodsOption
            $orderGoods = OrderGoods::whereIn('order_id', array_values($orderIds))
                ->with(['goodsOption', 'hasOneGoods'])
                ->get()
                ->groupBy('order_id');
            // 3. 查询提货地址
            $returnAddresses = ReturnAddress::whereIn('supplier_id', $suppIds)->where('is_refund', 2)->where('is_default', 1)->get()->keyBy('supplier_id');

            // 4. 构造最终统计结构
            $result = [];
            $total_volume = 0;
            $total_package = 0;
            foreach ($orderGoods as $orderId => $items) {
                $volume = 0;
                $package = 0;
                $max_lead_time = 0;

                foreach ($items as $item) {
                    if ($item->goodsOption) {
                        $volume += $item->goodsOption->volume ?? 0;
                        $package += $item->goodsOption->package_number ?? 0;
                    }

                    // 获取最大的 lead_time
                    if ($item->hasOneGoods && isset($item->hasOneGoods->lead_time)) {
                        $leadTime = (int)$item->hasOneGoods->lead_time;
                        if ($leadTime > $max_lead_time) {
                            $max_lead_time = $leadTime;
                        }
                    }
                }

                // 获取对应订单的 pay_time
                $order_obj = Order::find($orderId);  // 可以考虑预加载优化
                $pay_time = $order_obj->pay_time ?? null;
                $production_end_date = $pay_time ? $pay_time->copy()->addDays($max_lead_time)->toDateString() : '';


                $suppId = $orderIdMap[$orderId] ?? null;
                $pickAddress = $suppId && isset($returnAddresses[$suppId])
                    ? $returnAddresses[$suppId]
                    : '';
                $total_volume += $volume;
                $total_package += $package;
                $array = [
                    'volume' => $volume,
                    'package_number' => $package,
                    'delivery_address' => implode(' ', array_filter([
                        $pickAddress->province_name,
                        $pickAddress->city_name,
                        $pickAddress->district_name,
                        $pickAddress->street_name,
                        $pickAddress->address
                    ])),
                    "delivery_point" => $pickAddress->address_name,
                    "order_id" => $orderId,
                    'delivery_phone' => $pickAddress->contact . "/" . $pickAddress->mobile,
                    'delivery_date' => $production_end_date
                ];


                $result[] = $array;
            }
            $addressMap = Address::whereIn('id', [$order->supplier->province_id, $order->supplier->city_id, $order->supplier->city_id, $order->supplier->street_id])->pluck('areaname')->toArray();

            $data = [
                "order_sn" => $order->order_sn,
                "backend_button_models" => $order->backend_button_models,
                "order_status_data" => [
                    "dispense_status" => $order->dispense_status,
                    "create_time" => $order->create_time->format('Y-m-d H:i:s'),
                    "logistics_send_time" => $order->logistics_send_time?date("Y-m-d H:i:s",$order->logistics_send_time):"",
                    "logistics_sign_time" => $order->logistics_sign_time?date("Y-m-d H:i:s",$order->logistics_sign_time):"",
                    "publish_time" => $order->publish_time?date("Y-m-d H:i:s",$order->publish_time):"",
                    "bid_time" => $order->bid_time?date("Y-m-d H:i:s",$order->bid_time):"",
                    "quote_time" => $order->quote_time?date("Y-m-d H:i:s",$order->quote_time):"",
                ],
                'member' => $order->belongsToMember,

                "freight_price" => $order->freight_price,
                "receiv_address" => $receiv_address->address,
                "receiv_name" => $receiv_address->realname,
                'receiv_mobile' => $receiv_address->mobile,
                "total_volume" => $total_volume,
                "total_package" => $total_package,
                "point_data" => $result
            ];
            $data['advanceStr'] = $this->logisticsService->getLeadTime($order->id)['advanceStr'];
            $data['expect_deliver_time'] = $order->deliver_date;
            $data['min_freight_price'] = $order->supplierPrice->min('freight_price');
            $data['logistic_company_num'] = $order->supplierPrice->count();
            if($order->supplier){
                $data['supplier'] = [
                    "store_name" => $order->supplier->store_name,
                    "mobile" => $order->supplier->mobile,
                    "address"=>$addressMap[0].$addressMap[1].$addressMap[2].$addressMap[3].$order->supplier->address
                ];
            }

            return $this->successJson('ok', $data);

        }

        return view('feight.logistics.detail', ['id' => $order_id]);

    }


    public function deliveryBill(Request $request)
    {

        $this->validate([
            'id' => 'required|integer|min:0',
        ]);
        $id = $request->input('id');
        $order = Order::find($id);
        if (!$order) {
            throw new ShopException('未找到订单');
        }
        $orderGoods = OrderGoods::where('order_id', $id)
            ->with(['goodsOption', 'hasOneGoods'])
            ->get();
        $volume = 0;
        $package = 0;
        $max_lead_time = 0;
        foreach ($orderGoods as $orderGood) {
            $volume += $item->goodsOption->volume ?? 0;
            $package += $item->goodsOption->package_number ?? 0;
            $leadTime = (int)$item->hasOneGoods->lead_time;
            if ($leadTime > $max_lead_time) {
                $max_lead_time = $leadTime;
            }

        }
        $pay_time = $order->pay_time ?? null;
        $production_end_date = $pay_time
            ? $pay_time->copy()->addDays($max_lead_time + 1)->toDateString() . ' 16:00之前'
            : '';

        //生成物流单号
        if (!$order->logistics_order_sn) {
            $logisticOrderSn = generateLogisticsNumber();
            $order->logistics_order_sn = $logisticOrderSn;
            $order->save();
        } else {
            $logisticOrderSn = $order->logistics_order_sn;
        }

        //提货点
        $returnAddresses = ReturnAddress::where('supplier_id', $order->supp_id)->where('is_refund', 2)->where('is_default', 1)->first();
        $receiv_address = OrderAddress::where('order_id', $id)->first();
        $supplier = Supplier::where('id', $order->supp_id)->first();
        $data = [
            "order_sn" => $order->order_sn,
            "delivery_time" => $production_end_date,
            "receiv_address" => $receiv_address->address,
            "list" => [
                "delivery_order_sn" => $logisticOrderSn,
                "package" => $package,
                "volume" => $volume,
                "remark" => "所有货品"
            ],
            "delivery_info" => [
                "company_name" => $returnAddresses->address_name,
                "contact" => $returnAddresses->contact,
                "mobile" => $returnAddresses->mobile,
                "address" => implode(' ', array_filter([
                    $returnAddresses->province_name,
                    $returnAddresses->city_name,
                    $returnAddresses->district_name,
                    $returnAddresses->street_name,
                    $returnAddresses->address
                ])),

            ],
            "logistics_info" => [
                "logistics_company_name" => $supplier->store_name,
                "remark1" => "1、司机必须凭此文件提货，如果没有携带不得提货。",
                "remark2" => "2、司机签字前，以确认以上所提所有包装信息正确无误。",
                "remark3" => "3、货物供应商应在自提货后2小时内在平台上传货物信息。",
                "remark4" => "4、物流供应商应在自提货后24小时内在平台上传揽收凭证。",
            ]
        ];

        return $this->successJson('ok', $data);


    }


}