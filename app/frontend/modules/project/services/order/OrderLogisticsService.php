<?php

namespace app\frontend\modules\project\services\order;

use app\common\exceptions\ShopException;
use app\common\models\goods\ReturnAddress;
use app\common\models\kefu\ServiceUser;
use app\common\models\OrderAddress;
use app\common\models\project\InstallTracks;
use app\common\models\project\LogisticsTracks;
use app\common\models\Order;
use app\frontend\models\OrderGoods;
use Yunshop\Supplier\common\models\SupplierInstallPrice;
use Yunshop\Supplier\common\models\SupplierLogisticPrice;

/**
 * 订单物流服务
 * 负责：物流跟踪、安装跟踪、自提点、送货单
 */
class OrderLogisticsService
{
    /**
     * 获取物流轨迹
     */
    public function getLogisticsTrack(int $order_id): array
    {
        $data = LogisticsTracks::getTracks($order_id);
        $toarray = $data->toArray();
        if (!is_array($toarray['track_logs'])) {
            $toarray['track_logs'] = json_decode($data->track_logs, true);
        }

        $info = SupplierLogisticPrice::where('order_id', $order_id)->where("bidding_status", 4)->first();
        if ($info) {
            $toarray['customer_link'] = ServiceUser::getDistributeService(0);
        }

        return $toarray;
    }

    /**
     * 获取安装轨迹
     */
    public function getInstallTrack(int $order_id): array
    {
        $data = InstallTracks::getTracks($order_id);
        $toarray = $data->toArray();
        if (!is_array($toarray['track_logs'])) {
            $toarray['track_logs'] = json_decode($data->track_logs, true);
        }

        $info = SupplierInstallPrice::where('order_id', $order_id)->where("bidding_status", 4)->first();
        if ($info) {
            $toarray['customer_link'] = ServiceUser::getDistributeService(0);
        }

        return $toarray;
    }

    /**
     * 获取自提点信息
     */
    public function getPickupPoint(int $order_id): array
    {
        $data = Order::where('parent_id', $order_id)
            ->with(['supplier' => function ($query) {
                $query->select('id', 'store_name', 'customer_phone', 'mobile');
            }])
            ->get();

        $result = $data->map(function ($order) {
            $supplier = $order->supplier;

            $goodsReturnAddress = ReturnAddress::where('supplier_id', $supplier->id ?? 0)
                ->where('is_refund', 2)
                ->where('is_default', 1)
                ->first();

            $refund_address = $goodsReturnAddress
                ? $goodsReturnAddress->province_name .
                $goodsReturnAddress->city_name .
                $goodsReturnAddress->district_name .
                $goodsReturnAddress->street_name .
                $goodsReturnAddress->address
                : '';

            return [
                'total' => $order->goods_total,
                'order_id' => $order->id,
                'goods_volume' => $order->goods_volume,
                'refund_address' => $refund_address,
                'contact_phone' => $supplier->customer_phone ?: $supplier->mobile,
                'service_link' => ServiceUser::getDistributeService(0),
                'store_name' => $supplier->store_name
            ];
        });

        return [
            'count' => $data->count(),
            'point_data' => $result
        ];
    }

    /**
     * 送货单
     */
    public function deliveryBill(int $order_id): array
    {
        $order = Order::find($order_id);
        if (!$order) {
            throw new ShopException('未找到订单');
        }

        $orderGoods = \app\common\models\OrderGoods::where('order_id', $order_id)
            ->with(['goodsOption', 'hasOneGoods'])
            ->get();

        $volume = 0;
        $package = 0;
        $max_lead_time = 0;
        foreach ($orderGoods as $item) {
            $volume += $item->goodsOption->volume ?? 0;
            $package += $item->goodsOption->package_number ?? 0;
            $leadTime = (int)$item->hasOneGoods->lead_time;
            if ($leadTime > $max_lead_time) {
                $max_lead_time = $leadTime;
            }
        }

        $pay_time = $order->pay_time ?? null;
        $production_end_date = $pay_time
            ? $pay_time->copy()->addDays($max_lead_time + 1)->toDateString()
            : '';

        if (!$order->logistics_order_sn) {
            $logisticOrderSn = generateLogisticsNumber();
            $order->logistics_order_sn = $logisticOrderSn;
            $order->save();
        } else {
            $logisticOrderSn = $order->logistics_order_sn;
        }

        $returnAddresses = \app\backend\modules\goods\models\ReturnAddress::where('supplier_id', $order->supp_id)->where('is_refund', 2)->where('is_default', 1)->first();
        $receiv_address = OrderAddress::where('order_id', $order_id)->first();
        $supplierLogistic = SupplierLogisticPrice::where('order_id', $order->parent_id)->where('bidding_status', 4)->first();

        return [
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
                "logistics_company_name" => "",
                "remark1" => "1、司机必须凭此文件提货，如果没有携带不得提货。",
                "remark2" => "2、司机签字前，以确认以上所提所有包装信息正确无误。",
                "remark3" => "3、货物供应商应在自提货后2小时内在平台上传货物信息。",
                "remark4" => "4、物流供应商应在自提货后24小时内在平台上传揽收凭证。",
            ]
        ];
    }
}
