<?php

namespace app\frontend\modules\project\services\bidorder;

use app\common\exceptions\AppException;
use app\common\models\Address;
use app\common\models\order\Express;
use app\common\models\refund\RefundApply;
use app\common\modules\pcnotice\Template;
use app\frontend\models\Order;
use Yunshop\Supplier\common\models\Supplier;
use app\common\modules\payType\remittance\models\flows\RemittanceAuditFlow;
use app\common\modules\payType\remittance\models\process\RemittanceAuditProcess;

class BidOrderQueryService
{
    const PAGE_SIZE = 6;

    // 投标方式
    const BID_TYPE_FACTORY = 1;       // 工厂直投
    const BID_TYPE_AUTHORIZED = 2;    // 授权投标

    // 投标方式名称
    const BID_TYPE_NAMES = [
        self::BID_TYPE_FACTORY => '工厂直投',
        self::BID_TYPE_AUTHORIZED => '授权投标',
    ];

    // 订单类型
    const ORDER_TYPE_BID_FEE = 2;     // 标书制作费
    const ORDER_TYPE_BRAND_FEE = 3;   // 品牌使用费
    const ORDER_TYPE_DEPOSIT = 4;     // 项目保证金

    // 订单类型名称
    const ORDER_TYPE_NAMES = [
        self::ORDER_TYPE_BID_FEE => '标书制作费',
        self::ORDER_TYPE_BRAND_FEE => '品牌使用费',
        self::ORDER_TYPE_DEPOSIT => '项目保证金',
    ];

    // 退款原因
    const REFUND_REASONS = [
        '协商一致退款',
        '订单信息拍错（规格/尺寸/颜色等）',
        '不想要了',
        '地址/电话信息填写错误',
        '缺货',
    ];

    /**
     * 投标订单列表
     */
    public function getList(array $search): array
    {
        $member_id = \YunShop::app()->getMemberId();
        $query = app('OrderManager')->make('Order')->uniacid()->select("*")->with(['projectBid' => function ($query) {
            $query->withTrashed()->select("id", "name", "province_id", "city_id", "district_id", "address_detail", "project_area", "bid_type");
        }, 'hasOneRefundApply' => function ($query) {
            $query->select("id", "refund_sn", "price", "status", "apply_price", "reason", "content", "refund_time", "created_at");
        }, 'project' => function ($query) {
            $query->withTrashed()->select("id", "name");
        }])->where('uid', $member_id);
        $query->where('is_member_deleted', 0);
        if (isset($search['status']) && ($search['status'] !== '' || $search['status'] === 0)) {
            if ($search['status'] !== "all" && $search['status'] !== 4) {
                $query->where('status', $search['status']);
            } elseif ($search['status'] == 4) {
                $query->where('refund_id', '>', 0);
            }
        }

        if ($search['project_id']) {
            $query->where('project_id', $search['project_id'])->whereIn('order_type', [2, 3, 4]);
        }

        if (isset($search['refund_status']) && ($search['refund_status'] !== '' || $search['refund_status'] === 0)) {
            $query->where('refund_status', $search['refund_status']);
        }

        $search['start_date'] = $search['start_date'] ? strtotime($search['start_date']) : "";
        $search['end_date'] = $search['end_date'] ? strtotime(date('Y-m-d', strtotime($search['end_date'])) . ' 23:59:59') : null;
        if (!empty($search['start_date']) && !empty($search['end_date'])) {
            $query->whereBetween('create_time', [$search['start_date'], $search['end_date']]);
        } elseif (!empty($search['start_date'])) {
            $query->where('create_time', '>=', $search['start_date']);
        } elseif (!empty($search['end_date'])) {
            $query->where('create_time', '<=', $search['end_date']);
        }

        if ($search['name']) {
            if (is_numeric($search['name'])) {
                $query->where('id', $search['name']);
            } else {
                $query->whereHas('project', function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search['name'] . '%');
                });
            }
        }

        //投标方式
        if ($search['bid_type']) {
            $query->whereHas('projectBid', function ($query) use ($search) {
                $query->where('bid_type', $search['bid_type']);
            });
        }
        //订单类型
        if ($search['order_type']) {
            if ($search['order_type'] == "all") {
                $query->whereIn('order_type', [2, 3, 4]);
            } else {
                $query->where('order_type', $search['order_type']);
            }
        }

        $data = $query->orderBy('create_time', 'desc')->paginate(self::PAGE_SIZE);
        $provinceCityIds = $data->pluck('projectBid.province_id')
            ->merge($data->pluck('projectBid.city_id'))
            ->merge($data->pluck('projectBid.district_id'))
            ->unique()
            ->toArray();
        $addresses = Address::whereIn('id', $provinceCityIds)
            ->pluck('areaname', 'id')
            ->toArray();
        $data->transform(function ($item) use ($addresses) {
            $province_name = $addresses[$item->projectBid->province_id] ?? '';
            $city_name = $addresses[$item->projectBid->city_id] ?? '';
            $district_name = $addresses[$item->projectBid->district_id] ?? '';
            $item->hasOneRefundApply->created_at = date("Y-m-d H:i:s", $item->hasOneRefundApply->created_at);
            $item->projectAddressDetail = $province_name . $city_name . $district_name . $item->projectBid->address_detail;
            $item->bid_type_name = self::BID_TYPE_NAMES[$item->projectBid->bid_type] ?? '';
            $item->bid_information = $item->information ? yz_tomedia($item->information) : "";
            return $item;
        });
        return $data->toArray();
    }

    /**
     * 售后订单列表
     */
    public function getAfterSales(array $search)
    {
        $member_id = \YunShop::app()->getMemberId();

        $query = app('OrderManager')->make('Order')->whereIn('order_type', [2, 3, 4])->where('refund_id', '>', 0)->where('uid', $member_id)->with(['project' => function ($query) {
            $query->withTrashed()->select("id", "name");
        }, 'hasOneRefundApply']);

        if ($search['refund_status']) {
            $query->where('refund_status', $search['refund_status']);
        }

        if ($search['order_type']) {
            $query->where('order_type', $search['order_type']);
        }

        if ($search['name']) {
            $query->whereHas('project', function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search['name'] . '%');
            });
        }

        $list = $query->orderBy('create_time', 'desc')->paginate(self::PAGE_SIZE);

        $list->transform(function ($item) {
            return [
                'id' => $item->id,
                'refund_id' => $item->hasOneRefundApply->id,
                "project_name" => $item->project->name,
                'order_type' => $item->order_type,
                'refund_price' => $item->hasOneRefundApply->price,
                'status_name' => $item->hasOneRefundApply->status_name,
                'status' => $item->hasOneRefundApply->status,
                'create_time' => date("Y-m-d H:i:s", $item->hasOneRefundApply->created_at),
                'created_at' => date("Y-m-d H:i:s", $item->hasOneRefundApply->created_at),
            ];
        });

        return $list;
    }

    /**
     * 申请退款详情
     */
    public function getApplyRefund(int $id): array
    {
        $orderDetail = Order::select("id", "supp_id", "bid_id", "pay_time", "price", "order_sn")->with(['supplier' => function ($query) {
            $query->select("id", "store_name");
        }, 'projectBid' => function ($query) {
            $query->select("id", "name", "province_id", "city_id", "district_id", "address_detail", "bid_type");
        }])->find($id);
        $address = Address::whereIn('id', [$orderDetail->projectBid->province_id, $orderDetail->projectBid->city_id, $orderDetail->projectBid->city_id])->pluck('areaname')->toArray();
        $orderDetail->projectBid->project_address_detail = $address[0] . $address[1] . $address[2] . $orderDetail->projectBid->address_detail;
        $orderDetail->projectBid->bid_type_name = self::BID_TYPE_NAMES[$orderDetail->projectBid->bid_type] ?? '';
        $orderDetail->reason = self::REFUND_REASONS;
        return $orderDetail->toArray();
    }

    /**
     * 订单详情
     */
    public function getDetail(int $id): array
    {
        $order_detail = Order::where("id", $id)->with(['projectBid'])->first();
        if (!$order_detail) {
            throw new AppException("订单不存在");
        }

        $data['order_sn'] = $order_detail->order_sn;
        $refund_order = RefundApply::where('order_id', $id)->first();
        if ($refund_order) {
            $data['status'] = 4;
        } else {
            $data['status'] = $order_detail->status;
        }

        $data['project_name'] = $order_detail->project->name;
        $data['project_area'] = $order_detail->projectBid->project_area;
        $address_map = Address::whereIn('id', [$order_detail->projectBid->province_id, $order_detail->projectBid->city_id, $order_detail->projectBid->district_id])->pluck('areaname')->toArray();
        $data['project_address_detail'] = $address_map[0] . $address_map[1] . $address_map[2] . $order_detail->projectBid->address_detail;
        $data['bid_type_name'] = self::BID_TYPE_NAMES[$order_detail->projectBid->bid_type] ?? '';
        $data['supplier_name'] = Supplier::where('id', $order_detail->projectBid->supplier_id)->value('store_name');
        $data['create_time'] = $order_detail->create_time->format('Y-m-d H:i:s');
        $data['pay_time'] = $order_detail->pay_time ? $order_detail->pay_time->format('Y-m-d H:i:s') : "";
        $data['pay_amount'] = $order_detail->price;

        $data['order_name'] = self::ORDER_TYPE_NAMES[$order_detail->order_type] ?? '';

        $remittanceAuditFlow = RemittanceAuditFlow::first();
        $processBuilder = RemittanceAuditProcess::where('flow_id', $remittanceAuditFlow->id)->whereHas('remittanceRecord', function ($query) use ($order_detail) {
            $query->where('order_pay_id', $order_detail->order_pay_id);
        })->first();

        $data['information'] = $order_detail->information ? yz_tomedia($order_detail->information) : "";

        $data['state'] = $processBuilder->state;
        $data['order_pay_id'] = $order_detail->order_pay_id;
        return $data;
    }

    /**
     * 确认验收
     */
    public function confirmSign($order_id): bool
    {
        $order = Order::find($order_id);
        if (!$order) {
            throw new AppException("未找到订单");
        }
        $order->status = 3;
        $order->finish_time = time();
        $order->save();

        $option['related_id'] = $order->id;
        app('notification')->send(
            $order->uid,
            Template::ORDER,
            Template::ORDER_TYPE[$order->order_type],
            getNoticeTitle($order->order_type, "INSPECTION"),
            ['order_no' => $order->order_sn],
            $option
        );
        return true;
    }

    /**
     * 物流信息
     */
    public function getLogistic($order_id): array
    {
        $order = Order::with(['projectBid' => function ($query) {
            $query->select("id", "consignee", "consignee_mobile", "consignee_address_detail", "consignee_province_id", "consignee_city_id", "consignee_district_id");
        }])->find($order_id);
        if (!$order) {
            throw new AppException("找不到订单");
        }
        $db_express_model = Express::where('order_id', $order_id)->first();
        $data['consignee'] = $order->projectBid->consignee;
        $data['consignee_mobile'] = $order->projectBid->consignee_mobile;
        $where = [$order->projectBid->consignee_province_id, $order->projectBid->consignee_city_id, $order->projectBid->consignee_district_id];
        $addressMap = Address::whereIn('id', $where)->pluck('areaname', 'id');

        $data['consignee_address'] = implode(' ', array_filter([
            $addressMap[$order->projectBid->consignee_province_id] ?? '',
            $addressMap[$order->projectBid->consignee_city_id] ?? '',
            $addressMap[$order->projectBid->consignee_district_id] ?? '',
            $order->projectBid->consignee_address_detail
        ]));
        $data['express_company_name'] = $db_express_model->express_company_name;
        $data['express_sn'] = $db_express_model->express_sn;
        return $data;
    }
}
