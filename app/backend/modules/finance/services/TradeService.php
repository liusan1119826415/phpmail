<?php


namespace app\backend\modules\finance\services;


use app\backend\modules\order\models\Order;
use app\backend\modules\order\models\OrderPay;
use app\backend\modules\order\models\VueOrder;
use app\backend\modules\refund\models\RefundApply;
use app\common\exceptions\ShopException;
use app\common\models\Address;
use app\common\models\project\Project;
use Carbon\Carbon;
use Yunshop\Supplier\common\models\SupplierPayOrder;
use Illuminate\Support\Facades\DB;

class TradeService
{


    public function getList($search)
    {
        $query = Project::select('id', 'name', 'change_time', 'order_id', 'order_status')->with(['order' => function ($query) {
            $query->select("id", "status", "orderStatus", "goods_price", "price", "order_type", "choose_logistics", "choose_install", "discount_price", 'extra_enable', 'extra_amount','freight_price','install_price');
        }]);
        $cloneQuery = clone $query;
        if (!empty($search['project_name'])) {
            $keyword = trim($search['project_name']);

            if (is_numeric($keyword)) {
                // 如果是纯数字，查询 id
                $query->where('id', $keyword);
            } else {
                // 如果是字符串，模糊查询 name
                $query->where('name', 'like', '%' . $keyword . '%');
            }
        }
        if ($search['start_time'] && $search['end_time']) {
            $range = [strtotime($search['start_time']), strtotime($search['end_time'])];
            $query->whereBetween('change_time', $range);
        }

        // 根据 change_time 查询
        if (isset($search['time_type'])) {
            $now = Carbon::now();
            switch ((int)$search['time_type']) {
                case 1: // 今日
                    $query->whereDate('change_time', $now->toDateString());
                    break;
                case 2: // 昨日
                    $query->whereDate('change_time', $now->subDay()->toDateString());
                    break;
                case 3: // 近7天
                    $query->where('change_time', '>=', $now->subDays(6)->startOfDay());
                    break;
                case 4: // 近30天
                    $query->where('change_time', '>=', $now->subDays(29)->startOfDay());
                    break;
                case 5: // 近1年
                    $query->where('change_time', '>=', $now->subYear()->startOfDay());
                    break;
            }
        }
        $data = $query->orderBy('change_time', 'desc')->paginate(20);

        //计算订单的总收入


        $projectIds = $data->pluck('id')->toArray();



        $incomeMap = OrderPay::select('project_id', DB::raw('SUM(amount) as total_amount'))
            ->where('status', 1)
            ->where('income', 1)
            ->whereIn('project_id', $projectIds)
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id');

        $paycomeMap = OrderPay::select('project_id', DB::raw('SUM(amount) as total_amount'))
            ->where('status', 1)
            ->where('income', 2)
            ->whereIn('project_id', $projectIds)
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id');

        $data->transform(function ($item) use ($incomeMap, $paycomeMap) {
            // 请确保 $item->order 已预加载，避免 N+1
            $order = $item->order;
            if (!$order) {
                $item->order_total_price = 0;
            } elseif ($order->order_type == 1) {
                $price = $order->goods_price;
                if ($order->choose_logistics == 1) $price += $order->freight_price;
                if ($order->choose_install == 1) $price += $order->install_price;
                $item->order_total_price = $price;
            } elseif (in_array($order->order_type, [2, 3, 4])) {
                $item->order_total_price = $order->price;
            } elseif ($order->order_type == 5) {
                $price = $item->goods_price;
                if ($order->extra_enable == 1) $price += $order->extra_amount;
                $item->order_total_price = $price;
            }

            $item->income_amount = $incomeMap[$item->id]->total_amount ?? 0;
            $item->paycome_amount = $paycomeMap[$item->id]->total_amount ?? 0;


            $item->change_time = $item->change_time?date("Y-m-d H:i:s",$item->change_time):"";


            return $item;
        });
        // ✅ 汇总统计
       $summary = $this->getTotalPrice($cloneQuery);

        // 输出或返回
        return [
            'list' => $data,
            'summary' => $summary
        ];


    }


    public function detail($id)
    {
        $project = Project::select("id", "member_id", "name", "contact_name", "phone", "order_id", "province_id", "city_id", "district_id", "address_detail")->with(['member' => function ($query) {
            $query->select("uid", "nickname", "mobile");
        }])->find($id);

        $addressMap = Address::whereIn('id', [$project->province_id, $project->city_id, $project->district_id])->pluck('areaname')->toArray();

        $order_total_prices  = Order::select([
        DB::raw('SUM(
            CASE 
                WHEN order_type = 1 THEN 
                    goods_price
                    + IF(choose_logistics = 1, freight_price, 0)
                    + IF(choose_install = 1, install_price, 0)
                WHEN order_type = 5 THEN 
                    goods_price + IF(extra_enable = 1, extra_amount, 0)
                ELSE 
                    price
            END
        ) AS total_amount'),
        DB::raw('SUM(discount_price) AS total_discount')
    ])
        ->where('parent_id', 0)
        ->where('status','!=',-1)
        ->where('project_id', $id)
        ->first();
        //获取退款总金额
        $refund_total_prices = RefundApply::selectRaw("sum(price) as total_price")->where('order_id', $project->order_id)->where('status', 6)->first();

        //获取
        $totals = OrderPay::where('status', 1)
            ->selectRaw('
        SUM(CASE WHEN income = 1 THEN amount ELSE 0 END) as total_income,
        SUM(CASE WHEN income = 2 THEN amount ELSE 0 END) as total_expense
    ')->where('project_id',$id)
            ->first();
        $order_total_price = $order_total_prices->total_amount ?: 0;
        $order_total_discount = $order_total_prices->total_discount ?: 0;
        $refund_total_price = $refund_total_prices->total_price ?: 0;
        $total_income_price = $totals->total_income ?: 0;
        $total_expense_price = $totals->total_expense ?: 0;

        $data = [
            "project_info" => [
                "id" => $project->id,
                "name" => $project->name,
                "contact_name" => $project->contact_name,
                'mobile'=>$project->phone,
                "address_detail" => implode(" ", [
                    $addressMap[0],
                    $addressMap[1],
                    $addressMap[2],
                    $project->address_detail
                ])
            ],
            "member_info" => [
                "uid" => $project->member->uid,
                "nickname" => $project->member->nickname,
                "mobile" => $project->member->mobile
            ],
            "order_total_price" => $order_total_price,
            "order_total_discount" => $order_total_discount,
            "refund_total_price" => $refund_total_price,
            "total_income_price" => $total_income_price,
            "total_expense_price" => $total_expense_price,

            // 差额金额
            "balance_price" => $order_total_price - ($order_total_discount + $refund_total_price + $total_income_price),
        ];

        return $data;

    }

    public function getPayList($project_id, $pay_type)
    {


        $query = OrderPay::with(['supplier' => function ($query) {
            $query->select("id", "store_name");
        }, 'member' => function ($query) {
            $query->select("uid", "nickname");
        }])->where('project_id', $project_id)->where('status', 1);
        if ($pay_type == 1) {
            $query->whereIn('income_type', [1, 6, 7]);
        } else {
            $query->where('income_type', $pay_type);
        }

        $list = $query->orderBy('created_at', 'desc')->get();

        $order_total_obj = $this->calculateOrderAmountByType($project_id, $pay_type);

        $order_query = OrderPay::where('status', 1);

        if ($pay_type == 1) {
            $order_query->whereIn('income_type', [1, 6, 7]);
        } else {
            $order_query->where('income_type', $pay_type);
        }

        $totals = $order_query->selectRaw('
    SUM(CASE WHEN income = 1 THEN amount ELSE 0 END) as total_income,
    SUM(CASE WHEN income = 2 THEN amount ELSE 0 END) as total_expense
          ')->where('project_id',$project_id)->first();

        $data['list'] = $list;
        $data['summary']['order_total_price'] = $order_total_obj->total_amount?:0;
        $data['summary']['total_income_price'] = $totals->total_income?:0;
        $data['summary']['total_expense_price'] = $totals->total_expense?:0;

        $data['summary']['total_discount_price'] = $order_total_obj->total_discount?:0;
        $refund_total_prices = RefundApply::selectRaw("sum(price) as total_price,order_id")->whereHas('order',function ($query) use($pay_type,$project_id){
            $query->where("order_type",$pay_type)->where('parent_id',0)->where('parent_id',$project_id);
        })->where('status', 6)->first();

        //TODO还有已退款没有计算
        $data['summary']['total_refund_price'] = $refund_total_prices->total_price?:0;
        return $data;



    }





    private function calculateOrderAmountByType($project_id, $orderType)
    {
        $query = Order::where('project_id', $project_id)->where('parent_id',0)->where('status','!=',-1);

        switch ($orderType) {
            case 1:
                // goods_price + freight + install
                $query->select(DB::raw('
                SUM(
                    goods_price
                    + CASE WHEN choose_logistics = 1 THEN freight_price ELSE 0 END
                    + CASE WHEN choose_install = 1 THEN install_price ELSE 0 END
                ) as total_amount,
                SUM(discount_price) as total_discount
                
            '));
                break;

            case 5:
                // goods_price + extra_amount (if extra_enable)
                $query->select(DB::raw('
                SUM(
                    goods_price
                    + CASE WHEN extra_enable = 1 THEN extra_amount ELSE 0 END
                ) as total_amount,
                SUM(discount_price) as total_discount
            '));
                break;

            case 2:
            case 3:
            case 4:

                $query->select(DB::raw('SUM(goods_price) as total_amount,SUM(discount_price) as total_discount'));
                break;

            default:
                // 返回 0 或抛异常
                return 0;
        }

        $result = $query->where('order_type', $orderType)->first();
        return $result;
    }


    private function getTotalPrice($query)
    {
        // 获取符合条件的 project_id 列表
        $projectIds = (clone $query)->pluck('id')->toArray();

// 获取对应的 order_id


// 再从 order 表做金额计算

        $orderTotals = Order::select(DB::raw("
    SUM(
        CASE 
            WHEN order_type = 1 THEN 
                goods_price
                + CASE WHEN choose_logistics = 1 THEN freight_price ELSE 0 END
                + CASE WHEN choose_install = 1 THEN install_price ELSE 0 END
            WHEN order_type = 5 THEN 
                goods_price + CASE WHEN extra_enable = 1 THEN extra_amount ELSE 0 END
            WHEN order_type IN (2, 3, 4) THEN price
            ELSE goods_price
        END
    ) as total_order_price
"))
            ->whereIn('project_id', $projectIds)
            ->where('parent_id',0)
            ->whereIn('status',[1,2,3])
            ->first();

        $orderPayTotals = OrderPay::select(DB::raw('
    SUM(CASE WHEN income = 1 THEN amount ELSE 0 END) as total_income_amount,
    SUM(CASE WHEN income = 2 THEN amount ELSE 0 END) as total_paycome_amount
'))
            ->where('status', 1)

            ->whereIn('project_id', $projectIds)
            ->first();

        $summary = [
            'total_order_price' => $orderTotals->total_order_price ?? 0,
            'total_income_amount' => $orderPayTotals->total_income_amount ?? 0,
            'total_paycome_amount' => $orderPayTotals->total_paycome_amount ?? 0,
            'total_count'=>count($projectIds)
        ];

        return $summary;


    }

    public function getOrderDetail($project_id)
    {

        $order = Order::select("id","order_sn","order_type","status","orderStatus","goods_price","price","extra_enable","extra_amount","choose_logistics",
                              "choose_install","freight_price","install_price")->where('project_id',$project_id)->where('parent_id',0)->whereIn('status',[1,2,3])->get();

        $order = $order->map(function ($order) {
        if (!$order) {
            $order->order_total_price = 0;
        } elseif ($order->order_type == 1) {
            $price = $order->goods_price;
            if ($order->choose_logistics == 1) $price += $order->freight_price;
            if ($order->choose_install == 1) $price += $order->install_price;
            $order->order_total_price = $price;
        } elseif (in_array($order->order_type, [2, 3, 4])) {
            $order->order_total_price = $order->price;
        } elseif ($order->order_type == 5) {
            $price = $order->goods_price;
            if ($order->extra_enable == 1) $price += $order->extra_amount;
            $order->order_total_price = $price;
        } else {
            $order->order_total_price = $order->goods_price; // 默认处理
        }

        return $order;
       });

        return $order;

    }

    public function getPayVoucher($pay_id)
    {
        $data = OrderPay::with(['payOrderOne'])->find($pay_id);
        $data->thumb = $data->thumb?yz_tomedia($data->thumb):"";
        return $data;
    }

    public function getIncomeData($project_id)
    {
        $pay_type = request()->pay_type;
        $income = request()->income;
        $query = OrderPay::with(['supplier' => function ($query) {
            $query->select("id", "store_name");
        }, 'member' => function ($query) {
            $query->select("uid", "nickname");
        }])->where('project_id',$project_id)->where('status',1);
        if($pay_type == 1){
            $query->where('income',$income)->where('is_refund',0);
        }elseif($pay_type == 2){
            $query->where('is_refund',1);
        }elseif($pay_type == 3){
            $query->where('is_discount',1);
        }
        $list = $query->get();
        return $list;

    }





}