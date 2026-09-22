<?php

namespace app\frontend\modules\project\infrastructure;

use app\backend\modules\charts\models\Supplier;
use app\common\exceptions\AppException;

use app\common\exceptions\ShopException;
use app\common\models\Address;
use app\common\models\goods\ReturnAddress;
use app\common\models\kefu\ServiceUser;
use app\common\models\OrderAddress;
use app\common\models\PayType;
use app\common\models\project\InstallOrder;
use app\common\models\project\InstallTracks;
use app\common\models\project\LogisticsTracks;
use app\common\models\project\OrderStage;
use app\common\modules\payType\remittance\models\flows\RemittanceAuditFlow;
use app\common\modules\payType\remittance\models\process\RemittanceAuditProcess;
use app\common\modules\pcnotice\Template;
use app\common\services\Session;
use app\frontend\models\GoodsOption;
use app\frontend\models\Member;
use app\frontend\models\OrderGoods;
use app\frontend\models\OrderPay;
use app\frontend\modules\cart\models\MemberCart;
use app\frontend\modules\memberCart\MemberCartCollection;
use app\frontend\modules\project\models\Project;
use app\frontend\modules\project\repositories\OrderRepositoryInterface;
use app\common\models\Order;
use app\frontend\repositories\MemberAddressRepository;
use Illuminate\Support\Facades\Cache;
use Yunshop\Supplier\common\models\SupplierInstallPrice;
use Yunshop\Supplier\common\models\SupplierLogisticPrice;
use Illuminate\Support\Facades\Redis;

class OrderRepository extends BaseRepository implements OrderRepositoryInterface
{

    public function verifyBeforeOrder(int $project_id): array
    {
        $project = Project::find($project_id);
        if ($project->order_status == 1) {
            throw new AppException('订单已经生成');
        }

        $cartItems = MemberCart::where('project_id', $project_id)
            ->with(['goods' => function($query) {
                $query->withTrashed()->select('id', 'status', 'title');
            }, 'goodsOption' => function($query) {
                $query->select('id','title');
            }])
            ->get();

        if($cartItems->isEmpty()) {
            throw new AppException('项目还未配置商品数据');
        }

        // 收集下架商品和无效选项
        $offShelfGoods = [];
        $invalidOptions = [];

        foreach ($cartItems as $item) {
            // 检查商品是否下架
            if ($item->goods && $item->goods->status == 0) {
                $offShelfGoods[] = [
                    'id' => $item->id,
                    'floor_id' => $item->floor_id,
                    'space_id' => $item->space_id,
                    'goods_id' => $item->goods_id,
                    'goods_title' => $item->goods->title
                ];
            }

            // 检查商品选项是否存在（如果cart有option_id）
            if ($item->option_id && !$item->goodsOption) {
                $invalidOptions[] = [
                    'id' => $item->id,
                    'floor_id' => $item->floor_id,
                    'space_id' => $item->space_id,
                    'goods_id' => $item->goods_id,
                    'option_id' => $item->option_id,
                    'goods_title' => $item->goods ? $item->goods->title : '未知商品'
                ];
            }
        }

        // 优先返回无效选项的错误
        if (!empty($invalidOptions)) {
            $errorMsg = '以下商品选项无效或已删除：';
            foreach ($invalidOptions as $option) {
                $errorMsg .= "【商品:{$option['goods_title']} 选项ID:{$option['option_id']}】";
            }
            $errorMsg .= "，请修正后再下单";
            return [
                'status' => 0,
                'offShelf' => 2,
                'offShelfGoods' => $invalidOptions,
                'msg' => $errorMsg
            ];
        }

        // 然后检查下架商品
        if (!empty($offShelfGoods)) {
            $errorMsg = '以下商品已下架：';
            foreach ($offShelfGoods as $goods) {
                $errorMsg .= "【ID:{$goods['goods_id']} {$goods['goods_title']}】";
            }
            $errorMsg .= "，请替换或删除再下单";
            return [
                'status' => 0,
                'offShelf' => 1,
                'offShelfGoods' => $offShelfGoods,
                'msg' => $errorMsg
            ];
        }

        // 所有验证通过
        return ['status' => 1];
    }


    public function preOrder(int $project_id)
    {
        $project = Project::find($project_id);
        if ($project->order_status == 1) {
            throw new AppException('订单已经生成');
        }
        $trade = $this->getMemberCarts($project_id)->getTrade(Member::current());
        $trade->total_num = app('OrderManager')->make('MemberCart')->where('project_id', $project_id)->sum('total');
        return $trade;
    }


    public function createOrder(int $project_id)
    {
        $project = Project::find($project_id);
        if ($project->order_status == 1) {
            throw new AppException('订单已经生成');
        }
        $trade = $this->getMemberCarts($project_id)->getTrade(Member::current());
        $orderId = $trade->generate();

        //生成订单,触发事件
        //已下单更改订单状态

        $project->order_status = 1; //更改为已下单状态
        $project->order_id = $orderId;
        $project->save();
        $member_id = \YunShop::app()->getMemberId();
        Redis::del("user:{$member_id}:active_project_1");
        //app(MemberAddressRepository::class)->modelInstance()->where('uid', \YunShop::app()->getMemberId())->delete();
        return ['order_ids' => $orderId];
    }

    private function getWhere($status)
    {
        $data = [];
        switch ($status) {
            case 1:  //待确认图纸
                $data = ['status' => 4];
                break;
            case 2://待付预付款
                $data = ['status' => 0, 'orderStatus' => 0];
                break;
            case 3://生产中
                $data = ['status' => 1, 'orderStatus' => 1];
                break;
            case 4://待付尾款
                $data = ['status' => 1, 'orderStatus' => 2];
                break;
            case 5://待发货
                $data = ['status' => 1, 'orderStatus' => 3];
                break;
            case 6://待验收
                $data = ['status' => 2];
                break;
            case 7://交易完成
                $data = ['status' => 3];
                break;
            case 8://已关闭
                $data = ['status' => -1];
                break;
        }
        return $data;
    }


    public function getList(array $search): array
    {
        $member_id = \YunShop::app()->getMemberId();
        $query = app('OrderManager')->make('Order')->uniacid()->select("*")->with(['project' => function ($query) {
            $query->withTrashed()->select("id", "name");
        }, 'supplier' => function ($query) {
            $query->select("id", "store_name");
        }, 'hasOneRefundApply' => function ($query) {
            $query->select("id", "refund_sn", "price", "status", "apply_price", "reason", "content", "refund_time", "created_at");
        }])->where('uid', $member_id)->where('order_type', 1)->where('parent_id', 0);
        if ($search['is_member_deleted']) {
            $query->where('is_member_deleted', $search['is_member_deleted']);
        } else {
            $query->where('is_member_deleted', 0);
        }
        $search['start_date'] = $search['start_date'] ? strtotime($search['start_date']) : "";
        $search['end_date'] = $search['end_date'] ? strtotime($search['end_date']) : "";
        // 按创建时间范围搜索
        if (!empty($search['start_date']) && !empty($search['end_date'])) {
            $query->whereBetween('create_time', [$search['start_date'], $search['end_date']]);
        } elseif (!empty($search['start_date'])) {
            $query->where('create_time', '>=', $search['start_date']);
        } elseif (!empty($search['end_date'])) {
            $query->where('create_time', '<=', $search['end_date']);
        }

        if ($search['name']) {
            $query->whereHas('project', function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search['name'] . '%');
            });
        }

        if (isset($search['status']) && ($search['status'] !== '' || $search['status'] === 0)) {

            if ($search['status'] !== "all") {
                /*if($search['status'] == 0){
                    $query->where(function ($q) {
                        $q->where(function ($q1) {
                            $q1->where('status', 0)->where('orderStatus', 0); // 待付收款
                        })->orWhere(function ($q2) {
                            $q2->where('status', 1)->where('orderStatus', 2); // 待付尾款
                        });
                    });
                }elseif($search['status'] == 1){
                    $query->where('status',1)->where('orderStatus',3);
                }elseif($search['status'] == 4){
                    $query->where('status',1)->where('orderStatus',1);
                }elseif($search['status'] == 2){
                    $query->where('status',2)->where('orderStatus',3);
                }elseif($search['status'] == -1){
                    $query->where('status',-1);
                }*/
                $query->where($this->getWhere($search['status']));

            }

            /*if ($search['status'] == 5) {
                //退款订单
                $query->where('refund_id', '>', 0);
            }*/
        }
        $data = $query->orderBy('create_time', 'desc')->paginate(self::PAGE_SIZE);
        $close_order_day = \Setting::get('shop.trade.close_order_days');

        // Get all order IDs from the paginated data
        $orderIds = $data->pluck('id');

// Get counts for all orders at once
        $confirmedCounts = OrderGoods::whereIn('order_main_id', $orderIds)
            ->where('confirm_status', 1)
            ->where('upload_time', '>', 0)
            ->groupBy('order_main_id')
            ->selectRaw('order_main_id, count(*) as count')
            ->pluck('count', 'order_main_id');
        $data->transform(function ($item) use($close_order_day,$confirmedCounts){
            $item->confirm_num = $confirmedCounts[$item->id] ?? 0;
            if(in_array($item->status,[4,0]) && $close_order_day){
                $item->closeTimestamp = $item->create_time
                    ->addDays($close_order_day)
                    ->timestamp;
            }

            if ($item->new_status == 3) {
                $max_lead_time = 0;
                if ($item->orderMainGoods->isNotEmpty()) {
                    $max_lead_time = $item->orderMainGoods->max(function ($goods) {
                        return isset($goods->goods->lead_time) ? (int)$goods->goods->lead_time : 0;
                    });
                }

                if ($item->first_pay_time && $max_lead_time > 0) {
                    // 计算预计完成日期
                    $expected_finish_date = $item->first_pay_time->copy()->addDays($max_lead_time);
                    // 当前时间
                    $now = \Carbon\Carbon::now();
                    // 计算剩余天数
                    $remaining_days = $now->diffInDays($expected_finish_date, false); // false 表示允许负值

                    // 添加到 item 中方便返回
                    $item->expected_finish_date = $expected_finish_date->format('Y-m-d H:i:s');
                    $item->remaining_days_text = $remaining_days;
                } else {
                    $item->expected_finish_date = null;
                    $item->remaining_days_text = null;
                }
            }

            return $item;
        });

        return $data->toArray();

    }


    public function getAfterSalesOrder(array $search): array
    {
        $member_id = \YunShop::app()->getMemberId();
        $query = app('OrderManager')->make('Order')->uniacid()->select("*")->with(['project' => function ($query) {
            $query->select("id", "name");
        }, 'hasOneRefundApply' => function ($query) {
            $query->select("id", "refund_sn", "price", "status", "apply_price", "reason", "content", "refund_time", "created_at");
        }])->where('uid', $member_id)->where('order_type', 1)->where('parent_id', 0);

        $query->where('is_member_deleted', 0)->where('refund_status', '!=', 0);

        if (isset($search['refund_status']) && ($search['refund_status'] !== '' || $search['refund_status'] === 0)) {

            if ($search['refund_status'] !== "all") {
                $query->where('refund_status', $search['refund_status']);
            }
        }


        if ($search['name']) {
            $query->whereHas('project', function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search['name'] . '%');
            });
        }


        $data = $query->orderBy('create_time', 'desc')->paginate(self::PAGE_SIZE)->toArray();

        // 转换 created_at 时间格式
        foreach ($data['data'] as &$item) {
            if (!empty($item['has_one_refund_apply']['created_at'])) {
                $item['has_one_refund_apply']['created_at'] = date('Y-m-d H:i', $item['has_one_refund_apply']['created_at']);
            }
        }

        return $data;
    }

    //确认图纸
    public function confirmDraw(int $id): bool
    {
        try {
            $order_goods = OrderGoods::where('id', $id)->first();
            $order_goods->status = 1;
            $order_goods->save();
            $count = OrderGoods::where('order_id', $order_goods->order_id)->where('status', 0)->count();
            if ($count == 0) {
                $order = Order::find($order_goods->order_id);
                Order::whereIn('parent_id', $order->parent_id)->update(['status' => Order::WAIT_PAY]);
                Order::where('id', $order->parent_id)->update(['status' => Order::WAIT_PAY]);
            }
        } catch (\Exception $e) {
            throw new AppException($e->getMessage());
        }


    }


    public function getRecycleOrder(array $search): array
    {
        $member_id = \YunShop::app()->getMemberId();
        $query = app('OrderManager')->make('Order')->uniacid()->select("*")->with(['project' => function ($query) {
            $query->select("id", "name");
        }])->where('uid', $member_id)->where('parent_id', 0);

        $query->where('is_member_deleted', 1);

        $search['start_date'] = $search['start_date'] ? strtotime($search['start_date']) : "";
        $search['end_date'] = $search['end_date'] ? strtotime($search['end_date']) : "";
        // 按创建时间范围搜索
        if (!empty($search['start_date']) && !empty($search['end_date'])) {
            $query->whereBetween('create_time', [$search['start_date'], $search['end_date']]);
        } elseif (!empty($search['start_date'])) {
            $query->where('create_time', '>=', $search['start_date']);
        } elseif (!empty($search['end_date'])) {
            $query->where('create_time', '<=', $search['end_date']);
        }

        if ($search['name']) {
            $query->whereHas('project', function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search['name'] . '%');
            });
        }
        if ($search['status'] == 1) {
            $query->where('order_type', 1);
        } elseif ($search['status'] == 2) {
            $query->where('order_type', 5);
        } elseif ($search['status'] == 3) {
            $query->whereIn('order_type', [2, 3, 4]);
        }


        $data = $query->paginate(self::PAGE_SIZE);
        return $data->toArray();

    }


    /**
     * 从project_id 获取项目空间中商品
     * @return MemberCartCollection
     * @throws AppException
     */
    protected function getMemberCarts($project_id)
    {
        static $memberCarts;
        if (!isset($memberCarts)) {
            $memberCarts = app('OrderManager')->make('MemberCart')->where('project_id', $project_id)->get();

            $memberCarts = new MemberCartCollection($memberCarts);
            $memberCarts->loadRelations();
        }

        if ($memberCarts->isEmpty()) {
            throw new AppException('项目没有产品，请前往产品库选择产品');
        }
        return $memberCarts;
    }

    public function detail(int $order_id): array
    {

        $data = app('OrderManager')->make('Order')->where('id', $order_id)
            ->with(['myOrderAddress' => function ($query) {
                $query->select("id", "order_main_id", "realname", "address", "mobile");
            }, 'hasOneOrderPay', 'orderPayments'])->first();

        if ($data) {
            //获取待确认的图纸数量
            $data->confirm_num = OrderGoods::where('order_main_id',$order_id)->where('confirm_status',1)->where('upload_time','>',0)->count();
            $orderPayments = $data->orderPayments->toArray();
            $data->point_count = Order::where('parent_id', $order_id)->count();
            $formattedOrderPayments = [];
            $pay_amount = 0;
            $advance_payment = 0;
            $tail_payment = 0;
            $payable_amount = 0;

            $paid_amount = 0;
            $goods_pay_amount = 0;
            $data->orderPayments = $data->orderPayments->map(function ($payment) {
                $order_pay_id = $payment->pay_id;
                $process_status = -3;
                if ($order_pay_id) {
                    $remittanceAuditFlow = RemittanceAuditFlow::first(); // 注意：你可能需要按条件找，不然就是全表第一条

                    $processBuilder = RemittanceAuditProcess::where('flow_id', $remittanceAuditFlow->id)
                        ->whereHas('remittanceRecord', function ($query) use ($order_pay_id) {
                            $query->where('order_pay_id', $order_pay_id);
                        })
                        ->first();


                    if ($processBuilder) {
                        if ($processBuilder->state == "processing") {
                            $process_status = 0;
                        } elseif ($processBuilder->state == "completed") {
                            $process_status = 1;
                        } else {
                            $process_status = 2;
                        }
                    }
                }


                // 加到当前对象中（Laravel 集合/模型支持动态属性）
                $payment->process_status = $process_status;

                return $payment;
            });
            foreach ($orderPayments as $payment) {
                if ($payment['status'] == 1) {
                    $pay_amount += $payment['price'];
                    $goods_pay_amount +=$payment['price'];
                }

                if ($payment['stage'] == 1) {
                    $advance_payment = $payment['price'];
                }
                if ($payment['stage'] == 2) {
                    $tail_payment = $payment['price'];

                }
                if ($payment['stage'] == $data->payment_stage) {
                    $payable_amount = $payment['price'];
                }
                if ($data->status == -1) {
                    $status_name_one = "已关闭";
                } elseif ($payment['status'] == 0) {
                    $status_name_one = "待付款";
                } elseif ($payment['status'] == 1) {
                    $status_name_one = "已完成";
                }

                $order_pay_id = $payment['pay_id'];


                $formattedOrderPayments[] = [
                    "name" => $payment['stage'] == 1 ? "阶段一：预付款" : "阶段二：尾款",
                    "goods_num" => "",
                    "price" => $this->formatNumber($payment['price']),
                    "type" => $payment['stage'] == 1 ? 2 : 3,
                    "order_sn" => $payment['status'] == 1?OrderPay::where('id',$payment['pay_id'])->value('pay_sn'):"",
                    "status" => $payment['status'],
                    "pay_amount" => $this->formatNumber($payment['status'] == 1?$payment['price']:0),
                    "discount" => [],
                    "status_name" => $status_name_one,
                    "button" => $payment['status'] == 1 ? "申请售后" : "",

                    'order_pay_id' => $order_pay_id,
                    'pay_type_id' => $payment['pay_type_id']
                ];
            }
            $data->advance_payment = $this->formatNumber($advance_payment);
            $data->tail_payment = $this->formatNumber($tail_payment);
            $data->logictis_fee = $this->formatNumber($data->freight_price);
            $data->install_fee = $this->formatNumber($data->install_price);
            $data->goods_price = $this->formatNumber($data->goods_price);
            //获取自提点


            //获取物流信息
//            $supplier_logistic = SupplierLogisticPrice::with(['supplier'])->where('order_id',$data->id)->where('bidding_status',4)->first();
//
//            $supplier_install = SupplierInstallPrice::with(['supplier'])->where('order_id',$data->id)->where('bidding_status',4)->first();

            $supplier_logistic_lock = SupplierLogisticPrice::with(['supplier'])->where('order_id', $data->id)->where('lock_status', 1)->first();

            $supplier_install_lock = SupplierInstallPrice::with(['supplier'])->where('order_id', $data->id)->where('lock_status', 1)->first();
            if ($data->status >= 1 && $data->orderStatus == 3 && $data->choose_logistics == 1) {
                $logistic_status_name = "已支付";
                $logistic_status = 1;
                $pay_amount += $supplier_logistic_lock->freight_price;

            } else {
                $logistic_status_name = "";
                $logistic_status = 0;
            }


            //获取汇款支付状态
            if ($data->pay_type_id == 16 && $data->order_pay_id) {
                $order_pay_id = $data->order_pay_id;
                $remittanceAuditFlow = RemittanceAuditFlow::first();
                $processBuilder = RemittanceAuditProcess::where('flow_id', $remittanceAuditFlow->id)->whereHas('remittanceRecord', function ($query) use ($order_pay_id) {
                    $query->where('order_pay_id', $order_pay_id);
                })->first();

                $data->process_status = 0;
                if ($processBuilder->state == "processing") {
                    $data->process_status = 0;
                } elseif ($processBuilder->state == "completed") {
                    $data->process_status = 1;
                } else {
                    $data->process_status = 2;
                }
            }


            if ($data->status >= 1 && $data->orderStatus == 3 && $data->choose_install == 1) {
                $pay_amount += $supplier_install_lock->freight_price;
                $install_status = 1;
            } else {
                $install_status = 0;
            }


            // 追加一些假数据
            $fakeData = [
                [
                    "name" => "商品采购",
                    "goods_num" => $data->goods_total,
                    "price" => $this->formatNumber($data->goods_price),
                    "pay_amount" => $this->formatNumber($goods_pay_amount),
                    "order_sn" => $data->order_sn,
                    "type" => 1,
                    "status" => $data->new_status,
                    "discount" => [],
                    "status_name" => $data->status_name,
                    "button" => "商品清单"
                ]
            ];
            if ($data->status >= 1 && $data->orderStatus >= 2) {

                $fakeData[] = [
                    "name" => "物流运输",
                    "logistic_unit" => $supplier_logistic_lock->store_name ?: "",
                    "phone" => $supplier_logistic_lock->supplier->mobile,
                    "price" => $this->formatNumber($supplier_logistic_lock->freight_price ?: 0),
                    "pay_amount" => $this->formatNumber(($data->status >= 1 && $data->orderStatus == 3 && $data->choose_logistics == 1)?$supplier_logistic_lock->freight_price:0),
                    "order_sn" => "",
                    "type" => 4,
                    "status" => $logistic_status,
                    'wait_status' => $supplier_logistic_lock ? 1 : 0,
                    "discount" => [
                        /*[
                            "name"=>"安装店铺优惠",
                            "value"=>100
                        ]*/
                    ],
                    "status_name" => $logistic_status_name,
                    "button" => "物流信息"
                ];


                $fakeData[] = [
                    "name" => "安装搬运",
                    "logistic_unit" => $supplier_install_lock->store_name,
                    "phone" => $supplier_install_lock->supplier->mobile,
                    "price" => $this->formatNumber($supplier_install_lock->freight_price ?: 0),
                    "pay_amount" => $this->formatNumber(($data->status >= 1 && $data->orderStatus == 3 && $data->choose_install == 1)?$supplier_install_lock->freight_price:0),
                    "order_sn" => "",
                    "type" => 5,
                    "status" => $install_status,
                    'wait_status' => $supplier_install_lock ? 1 : 0,
                    "discount" => [

                    ],
                    "status_name" => $logistic_status_name,
                    "button" => "安装信息"
                ];

            }

            $formattedOrderPayments = array_merge($formattedOrderPayments, $fakeData);
            $data->pay_amount = $this->formatNumber($pay_amount); //已经付款
            $data->orderPayments = $formattedOrderPayments;
            $data->payable_amount = $this->formatNumber(number_format((float) $payable_amount, 2, '.', ''));
        }
        //阶段

        return $data->toArray();
    }


    public function delete(int $id): bool
    {
        try {

            $order = Order::find($id);
            if ($order->status != -1) {
                throw new AppException("请取消订单再删除");
            }
            if (!$order) {
                throw new AppException("未找到订单");
            }
            // 真实删除（永久删除数据）
            $order->delete();
            return true;
        } catch (\Exception $e) {
            throw new AppException($e->getMessage());
        }
    }

    public function recycle(int $id): bool
    {
        try {
            $order = Order::find($id);
            if ($order->status != -1) {
                throw new AppException("请取消订单再删除");
            }
            if (!$order) {
                throw new AppException("未找到订单");
            }
            // 真实删除（永久删除数据）
            $order->is_member_deleted = 0;
            $order->save();
            return true;
        } catch (\Exception $e) {
            throw new AppException($e->getMessage());
        }
    }

    /*public function batchConfirm(array $ids):bool
    {

        try {
            $order_id = OrderGoods::where('id',$ids[0])->value("order_id");
            $order_main_id = Order::where('id',$order_id)->value("id");

            OrderGoods::whereIn('id',$ids)->update(["confirm_status"=>2,'confirm_time'=>time()]);
            //更改主订单状态
            Order::where('id',$order_main_id)->update(['status'=>0]);
            Order::where('parent_id',$order_main_id)->update(['status'=>0,'confirm_time'=>time(),'confirm_status'=>1]);
            return true;
        }catch (\Exception $e){
            throw new ShopException($e->getMessage());
        }


    }*/

    public function batchConfirm(array $ids): bool
    {

        if (!$ids) {
            throw new AppException("请选择商品");
        }
        try {
            // 1. 获取本次更新涉及的子订单 IDs
            $orderIds = OrderGoods::whereIn('id', $ids)
                ->pluck('order_id')
                ->unique()
                ->toArray();

            if (empty($orderIds)) {
                return false;
            }

            // 2. 查询主订单 ID（假设所有子订单属于同一个主订单）
            $parentId = Order::where('id', $orderIds[0])->value('parent_id');


            $expectedCount = count($ids);
            $actualCount = OrderGoods::whereIn('id', $ids)
                ->where('confirm_status', 1)
                ->count();

            if ($actualCount !== $expectedCount) {
                throw new ShopException('存在未上传图纸的订单商品');
            }

            // 3. 更新本次商品为已确认
            OrderGoods::whereIn('id', $ids)->where('confirm_status', 1)->update([
                'confirm_status' => 2,
                'confirm_time' => time(),
            ]);

            // 4. 对每一个子订单，检查其是否所有商品都确认了，如果是，更新其状态
            foreach ($orderIds as $orderId) {
                $total = OrderGoods::where('order_id', $orderId)->count();
                $confirmed = OrderGoods::where('order_id', $orderId)
                    ->where('confirm_status', 2)
                    ->count();

                if ($total > 0 && $total == $confirmed) {
                    Order::where('id', $orderId)->update([
                        'status' => 0,
                        'confirm_status' => 1,
                        'confirm_time' => time(),
                    ]);
                }
            }

            // 5. 判断主订单下所有子订单是否都已确认（confirm_status = 1）
            $childOrderIds = Order::where('parent_id', $parentId)->pluck('id')->toArray();

            $childCount = count($childOrderIds);
            $confirmedCount = Order::whereIn('id', $childOrderIds)
                ->where('confirm_status', 1)
                ->count();

            if ($childCount > 0 && $childCount == $confirmedCount) {
                Order::where('id', $parentId)->update([
                    'status' => 0,
                    'confirm_status' => 1,
                    'confirm_time' => time(),
                ]);
            }

            return true;
        } catch (\Exception $e) {
            throw new ShopException($e->getMessage());
        }
    }

    //撤销确认图纸
    public function cancelConfirm(int $order_goods_id): bool
    {
        try {
            $orderGoods = OrderGoods::find($order_goods_id);
            $orderId = $orderGoods->order_id;
            $order = Order::where('id', $orderId)->first();
            if ($order->status >= 1 && $order->status != 4) {
                throw new ShopException("该订单已经支付，无法撤销");
            }
            if (!$orderGoods) {
                throw new ShopException("订单商品不存在");
            }

            if ($orderGoods->confirm_status !== 2) {
                throw new ShopException("该订单商品未处于已确认状态，无法撤销");
            }

            // 撤销该商品确认状态
            $orderGoods->confirm_status = 1;
            $orderGoods->confirm_time = 0;
            $orderGoods->save();


            // 撤销该订单的状态为

            Order::where('id', $orderId)->update([
                'confirm_status' => 0,
                'confirm_time' => 0,
                'status' => Order::WAIT_CONFIRM
            ]);


            // 获取主订单 ID
            $parentId = Order::where('id', $orderId)->value('parent_id');

            if ($parentId) {
                // 主订单撤销确认
                Order::where('id', $parentId)->update([
                    'confirm_status' => 0,
                    'confirm_time' => null,
                    'status' => Order::WAIT_CONFIRM
                ]);

            }

            return true;

        } catch (\Exception $e) {
            throw new ShopException($e->getMessage());
        }
    }


    //查看生产进度
    public function getProductSchedule(int $project_id): array
    {
        $order_main_id = request()->order_main_id;
        //$order_ids = Order::where('parent_id', $order_main_id)->pluck("id")->toArray();
        $query = OrderGoods::select("id", "order_id", "title", "goods_option_title","type","goods_option_id", "product_sn", "goods_id", "thumb", "total", "components");
        if ($project_id) {
            $query->where('project_id', $project_id);
        }
        if ($order_main_id) {
            $query->where('order_main_id', $order_main_id);
        }
        $orderGoods = $query->with(['hasOneGoods' => function ($query) {
            $query->select("id", "lead_time", "supp_id")
                ->with(['supplierGoods' => function ($query) {
                    $query->select("id", "store_name");
                }]);
        },'goodsOption'=>function($query){
            $query->select("id","length","width","height");
        }])
            ->get();

        // 重构数据结构
        $groupedData = $orderGoods->groupBy(fn($item) => $item->hasOneGoods->supplierGoods->id ?? 0)
            ->map(function ($items, $supp_id) {

                $max_lead_time = $items->max(fn($item) => $item->hasOneGoods->lead_time ?? 0);

                // 计算预计发货日期
                $pay_time = optional($items->first()->order)->first_pay_time;
                $ship_date = $pay_time ? date('Y-m-d', strtotime($pay_time . " +{$max_lead_time} days")) : null;

                // 计算剩余天数
                $remaining_days = $ship_date ? max(0, ceil((strtotime($ship_date) - time()) / 86400)) : null;
                $order_id = optional($items->first())->order_id;
                $order = Order::find($order_id);

                $product_status = 0;
                if ($order && $order->status >= 1 && $order->orderStatus >= 2) {
                    $product_status = 1;
                }
                if ($order && $order->status == 1 && $order->orderStatus == 1) {
                    $product_status = 2;
                }
                $componentData = $items->toArray()?$items->toArray()[0]:[];
                return [
                    'id' => $supp_id,
                    'store_name' => $items->first()->hasOneGoods->supplierGoods->store_name ?? '',
                    'service_link' => ServiceUser::getDistributeService($supp_id),
                    'estimated_ship_date' => $ship_date,  // 预计发货时间
                    'remaining_days' => $remaining_days,  // 剩余天数
                    'material_color' => $this->getMaterialColor($componentData),
                    'product_status' => $product_status,

                    'data' => $items->toArray(),

                ];
            })
            ->values()
            ->toArray();

        return $groupedData;
    }


    public function getDrawList(array $search): array
    {

        $memberId = \YunShop::app()->getMemberId();
        $query = app('CartContainer')->make('MemberCart')->floor()
            ->where(app('CartContainer')->make('MemberCart')->getTable() . '.member_id', $memberId);
        $query->where(app('CartContainer')->make('MemberCart')->getTable() . '.project_id', $search['project_id']);
        if ($search['floor_id']) {
            $query = $query->where(app('CartContainer')->make('MemberCart')->getTable() . '.floor_id', $search['floor_id']);
        }

        if ($search['upload_status']) {
            $query = $query->where(app('CartContainer')->make('MemberCart')->getTable() . '.upload_status', $search['upload_status']);
        }


        $data = $query->orderBy(app('CartContainer')->make('MemberCart')->getTable() . '.created_at', 'desc')
            ->get()->toArray();


        $result = collect($data)->groupBy('belongs_to_floor.id')->map(function ($floorItems, $floorId) {
            $floorName = $floorItems->first()['belongs_to_floor']['floor_name'];

            // 按空间分组
            $spaces = collect($floorItems)->groupBy('belongs_to_space.id')->map(function ($spaceItems, $spaceId) {
                $spaceName = $spaceItems->first()['belongs_to_space']['space_name'];

                $goodsData = $spaceItems->map(function ($item) {
                    return [
                        "id" => $item['id'],
                        'goods_id' => $item['goods']['id'],
                        'title' => $item['goods']['title'],
                        'upload_status' => $item['upload_status'],
                        "upload_time" => !empty($item['upload_time']) ? date("Y-m-d H:i:s", $item['upload_time']) : "",
                        "confirm_time" => !empty($item['confirm_time']) ? date("Y-m-d H:i:s", $item['confirm_time']) : "",
                        "total" => $item['total'],
                        "customer_url" => "https://kefu.abangmi.com/im/kefu/index/index",
                        "store_name" => $item['goods']['supplierGoods']['store_name'],
                        "thumb" => yz_tomedia($item['goods_option']['thumb']),
                        "product_price" => $item['goods_option']['product_price'],
                        "unit_price" => $item['goods_option']['market_price'],
                        "total_price" => $item['total'] * $item['goods_option']['market_price'],
                        "status" => $this->getGoodsStatus($item),
                        "sku" => $item['goods']['sku'],
                        "type" => $item['type'],
                        "length" => $item['goods_option']['length'],
                        "width" => $item['goods_option']['width'],
                        "height" => $item['goods_option']['height'],
                        "product_model" => $item['goods_option']['product_model'],
                        "material_color" => $this->getMaterialColor($item),
                        "structure" => $item['goods']['structure'],
                        "volume" => $item['goods_option']['volume'],
                        "jsonData" => !empty($item['jsonData']) ? json_decode($item['jsonData'], true) : [],
                        "modelData" => !empty($item['modelData']) ? json_decode($item['modelData'], true) : [],

                    ];
                })->values();
                $spaceTotalPrice = $goodsData->sum('total_price');
                $spaceTotalNum = $goodsData->sum('total');
                return [
                    'space_id' => $spaceId,
                    'space_name' => $spaceName,
                    'goods' => $goodsData,
                    'space_total_price' => $spaceTotalPrice,
                    'space_total_num' => $spaceTotalNum,
                ];
            })->values();
            $floorTotalPrice = $spaces->sum('space_total_price');
            $floorTotalNum = $spaces->sum('space_total_num');
            return [
                'floor_id' => $floorId,
                'floor_name' => $floorName,
                'space' => $spaces,
                'floor_total_price' => $floorTotalPrice,
                'floor_total_num' => $floorTotalNum,

            ];
        })->values();
        $projectTotalPrice = $result->sum('floor_total_price');
        $projectTotalNum = $result->sum('floor_total_num');
        return [
            'project_total_price' => $projectTotalPrice,
            'project_total_num' => $projectTotalNum,
            'data' => $result->toArray()
        ];
    }


    public function getOrderGoods(array $search): array
    {
        $member_id = \YunShop::app()->getMemberId();
        $cacheKey = "order_goods_list_{$member_id}_{$search['order_id']}";
//        $data = Cache::get($cacheKey);
//        if($data){
//            return $data;
//        }
        $data = $this->getGoods($search);

        // Cache::put($cacheKey, $data, 24 * 60 + rand(10, 99));  // 写入新缓存
        return $data;

    }


    protected function getGoods(array $search): array
    {
        $query = \app\common\models\OrderGoods::with([
            'floors:id,name',
            'spaces:id,name',
            'order' => function ($query) {
                $query->select("id", "order_sn", "supp_id")->with(['supplier' => function ($query) {
                    $query->select("id", "store_name");
                }]);
            },
            'goodsOption' => function($query) {
                $query->select("id", "length", "width", "height", "structure");
            },
            /*'orderGoodsChildrens' => function($query) {
                $query->select("*")
                    ->with(['goodsOption' => function($query) {
                        $query->select("id", "length", "width", "height", "structure");
                    }]);
            }*/
        ])->where('refund_success', 0);

        if ($search['order_id']) {
            $query->where('order_id', $search['order_id']);
        }

        if ($search['order_main_id']) {
            $order_ids = Order::where('parent_id', $search['order_main_id'])->pluck('id')->toArray();
            $query->whereIn('order_id', $order_ids);
        }

        if ($search['project_id']) {
            $query->where('project_id', $search['project_id'])->whereHas('order', function ($query) {
                $query->where('status', '!=', -1);
            });
        }

        if (isset($search['upload_status']) && ($search['upload_status'] !== '' || $search['upload_status'] === 0)) {
            $query->where('upload_status', $search['upload_status']);
        }

        $OrderGoods = $query->get()
            ->groupBy(fn($item) => $item->floors->name ?? '未分配楼层')
            ->map(function ($floorGroup, $floorName) {
                return [
                    'id' => $floorGroup->first()->floor_id ?? 0,
                    'name' => $floorName,
                    'data' => $floorGroup->groupBy(fn($item) => $item->spaces->name ?? '未分配空间')
                        ->map(function ($spaceGroup, $spaceName) {
                            return [
                                'id' => $spaceGroup->first()->space_id ?? 0,
                                'name' => $spaceName,
                                'data' => $spaceGroup->map(function ($item) {
                                    // 处理子商品数据
                                    $childrenData = [];
                                   /* if ($item->orderGoodsChildrens && $item->orderGoodsChildrens->isNotEmpty()) {
                                        foreach ($item->orderGoodsChildrens as $child) {
                                            $childrenData[] = [
                                                'id' => $child->id,
                                                'title' => $child->title,
                                                'product_price' => $child->product_price,
                                                'total_price' => $child->total_price,
                                                'upload_status' => $child->upload_status,
                                                'length' => $child->goodsOption->length ?? null,
                                                'width' => $child->goodsOption->width ?? null,
                                                'height' => $child->goodsOption->height ?? null,
                                                'structure' => $child->structure ?? null,
                                                'goods_option_title'=>$child->goods_option_title,
                                                'service_link'=>ServiceUser::getDistributeService($item->order->supp_id, $child->goods_id),
                                                'store_name'=>$item->order->supplier->store_name,
                                                'material_color'=>$this->getMaterialColor($child->toArray()),
                                                'draw_file'=>$child->draw_file ? array_map(function ($value) {
                                                    return yz_tomedia($value);
                                                }, unserialize($child->draw_file)) : [],
                                                'upload_time'=>$child->upload_time ? date("Y-m-d H:i:s", $child->upload_time) : "",
                                                'confirm_time'=>$child->confirm_time ? date("Y-m-d H:i:s", $child->confirm_time) : "",
                                            ];
                                        }
                                    }*/

                                    // 给$item增加客服链接
                                    $item->service_link = ServiceUser::getDistributeService($item->order->supp_id, $item->goods_id);
                                    $item->structure = $item->goodsOption->structure ?? null;
                                    $item->store_name = $item->order->supplier->store_name ?? '';
                                    $item->material_color = $this->getMaterialColor($item->toArray());
                                    $item->draw_file = $item->draw_file ? array_map(function ($value) {
                                        return yz_tomedia($value);
                                    }, unserialize($item->draw_file)) : [];
                                    $item->upload_time = $item->upload_time ? date("Y-m-d H:i:s", $item->upload_time) : "";
                                    $item->confirm_time = $item->confirm_time ? date("Y-m-d H:i:s", $item->confirm_time) : "";
                                    $item->option_children = $childrenData; // 使用子商品数据替换原来的 oldOptionData

                                    return $item;
                                })->values()->all(),
                            ];
                        })->values()->all(),
                ];
            })->values()->all();

        return $OrderGoods;
    }


    public function getAfterSalesOrderDetail(int $order_id): array
    {

        $order = Order::select("id", "order_sn", "project_id", "goods_total", "goods_price", "create_time", "pay_time")->with(['project' => function ($query) {
            $query->select("id", "name", "province_id", "city_id", "district_id", "address_detail");
        }])->where('id', $order_id)->first();

        $orderIds = Order::where('parent_id', $order_id)->pluck("id")->toArray();
        //获取子订单售后商品
        $orderGoods = \app\common\models\OrderGoods::whereIn('order_id', $orderIds)->where('refund_id', '>', 0)->with(['order' => function ($query) {
            $query->select("id", "order_sn", "supp_id")->with(['supplier' => function ($query) {
                $query->select("id", "store_name");
            }]);
        }, 'hasOneRefund' => function ($query) {
            $query->select("id", "refund_sn", "apply_price", "price", "refund_type", "status", "create_time");
        }])->get();
        $address = Address::whereIn('id', [$order->project->province_id, $order->project->city_id, $order->project->district_id])->pluck("areaname")->toArray();
        $order->project->project_address_detail = $address[0] . $address[1] . $address[2] . $order->project->address_detail;
        $orderGoods = $orderGoods->map(function ($item) {
            $item->material_color = $this->getMaterialColor($item->toArray());

            $item->order->supplier->service_link = ServiceUser::getDistributeService($item->order->supplier->id, $item->goods_id);
            return $item;
        });


        // 按供应商分组
        $groupedOrderGoods = $orderGoods->groupBy(function ($item) {
            return $item->order->supplier->id; // 以 supplier_id 作为分组键
        })->map(function ($items, $supplierId) {

            return [
                'id' => $supplierId,
                'name' => $items->first()->order->supplier->store_name, // 获取分组的供应商名称
                'service_link' => ServiceUser::getDistributeService($supplierId),
                "refund_sn" => $items->first()->hasOneRefund->refund_sn,
                'data' => $items->values(), // 返回对应的 orderGoods 数据
            ];
        })->values(); // 重新索引数组

        $order->orderGoods = $groupedOrderGoods;
        return $order->toArray();


    }

    public function getLogisticsTrack(int $order_id): array
    {
        $data = LogisticsTracks::getTracks($order_id);
        $toarray = $data->toArray();
        if (!is_array($toarray['track_logs'])) {
            $toarray['track_logs'] = json_decode($data->track_logs, true);
        }
        $info = SupplierLogisticPrice::where('order_id', $order_id)->where("bidding_status", 4)->first();

        if ($info) {
            $toarray['customer_link'] = ServiceUser::getDistributeService($info->supplier_id);
        }

        return $toarray;
    }

    public function getInstallTrack(int $order_id): array
    {
        $data = InstallTracks::getTracks($order_id);
        $toarray = $data->toArray();
        if (!is_array($toarray['track_logs'])) {
            $toarray['track_logs'] = json_decode($data->track_logs, true);
        }

        $info = SupplierInstallPrice::where('order_id', $order_id)->where("bidding_status", 4)->first();

        if ($info) {
            $toarray['customer_link'] = ServiceUser::getDistributeService($info->supplier_id);
        }

        return $toarray;
    }


    //获取汇款支付结果
    public function getRemittanceResult($order_pay_id): array
    {

        $orderPay = \app\frontend\models\OrderPay::find($order_pay_id);
        $order_first = $orderPay->orders->first();
        $data = [];
        if ($orderPay->pay_type_id == 16) {
            $data = [

                "remittance_data" => [
                    ['title' => '收款公司',
                        'text' => \Setting::get('shop.pay.remittance_bank_account_name')
                    ],
                    ['title' => '银行账号',
                        'text' => \Setting::get('shop.pay.remittance_bank_account')
                    ],
                    ['title' => '开户行',
                        'text' => \Setting::get('shop.pay.remittance_bank')
                    ],
                    ['title' => '行号',
                        'text' => \Setting::get('shop.pay.remittance_sub_bank')
                    ],
                ]

            ];
            $remittanceAuditFlow = RemittanceAuditFlow::first();

            $processBuilder = RemittanceAuditProcess::where('flow_id', $remittanceAuditFlow->id)->whereHas('remittanceRecord', function ($query) use ($order_pay_id) {
                $query->where('order_pay_id', $order_pay_id);
            })->first();


            $process_status = 0;
            if ($processBuilder->state == "processing") {
                $process_status = 0;
            } elseif ($processBuilder->state == "completed") {
                $process_status = 1;
                $data['pay_finish_time'] = $processBuilder->updated_at->format('Y.m.d H:i:s');
            } else {
                $process_status = 2;
            }
            $data['status'] = $process_status;
            $data['status_name'] = $processBuilder->status_name;
            $data['pay_time'] = $processBuilder->created_at->format('Y.m.d H:i:s');
        } else {
            $data['status'] = $orderPay->status;
            $data['status_name'] = $orderPay->status == 1 ? "支付成功" : "等待支付结果";
            $data['pay_time'] = $orderPay->pay_time ? $orderPay->pay_time->format('Y.m.d H:i:s') : "";

        }

        $data['pay_type_name'] = $orderPay->pay_type_name;
        $data['order_sn'] = $order_first->order_sn;
        $data['pay_type'] = $orderPay->pay_type_id;

        //  $orderStages = OrderStage::where('order_id',$order_first->id)->get();
        /*$payAmount = 0;
        foreach ($orderStages as $stage) {
            if ($stage->stage == $orderPay->payment_stage) {
                $payAmount = $stage->price;
            }

        }*/
        $payAmount = $this->formatNumber($orderPay->amount);
        $total_amount = 0;

        if ($order_first->order_type == 1) {
            $data['pay_data'][] =
                [
                    "name" => $orderPay->payment_stage == 1 ? "预付款金额" : "尾款金额",
                    "amount" => $payAmount
                ];

            $total_amount += $payAmount;
            if ($orderPay->payment_stage == 2) {
                $supplier_logistic_lock = SupplierLogisticPrice::where('order_id', $order_first->id)->where('lock_status', 1)->first();
                $supplier_install_lock = SupplierInstallPrice::where('order_id', $order_first->id)->where('lock_status', 1)->first();
                $freight_price = 0;
                $install_price = 0;
                $freight_price = $supplier_logistic_lock ? $supplier_logistic_lock->freight_price : 0;
                $install_price = $supplier_install_lock ? $supplier_install_lock->freight_price : 0;
                if ($order_first->choose_logistics == 1) {
                    $data['pay_data'][] =
                        [
                            "name" => "物流运输费",
                            "amount" => $freight_price,
                        ];
                }

                if ($order_first->choose_install == 1) {
                    $data['pay_data'][] =
                        [
                            "name" => "安装搬运费",
                            "amount" => $install_price,
                        ];
                }

                $logistic_price = $order_first->choose_logistics == 1 ? $freight_price : 0;
                $install_price = $order_first->choose_install == 1 ? $install_price : 0;
                $total_amount += $this->formatNumber($logistic_price + $install_price);

            }

            $data['pay_data'][] =
                [
                    "name" => "支付总额",
                    "amount" => $total_amount,
                ];
            $data['total_pay_amount'] = $total_amount;
        } else {
            $data['pay_data'] = [
                [
                    "name" => "支付总额",
                    "amount" => $this->formatNumber($order_first->price)
                ],
            ];
            $data['total_pay_amount'] = $this->formatNumber($order_first->price);
        }

        $data['order_type'] = $order_first->order_type;
        return $data;
    }


    //确认验收
    public function confirmSign(int $order_main_id): bool
    {
        try {
            $order = Order::find($order_main_id);
            if (!$order) {
                throw new AppException("订单未找到");
            }
            $order->status = 3;
            $order->finish_time = time();
            $order->save();

            //更新安装状态
            InstallOrder::where('order_id', $order_main_id)->update(
                [
                    "install_status" => 2
                ]
            );
            //更新子订单状态
            Order::where('parent_id', $order_main_id)->update(
                [
                    "status" => 3,
                    "finish_time" => time()
                ]
            );

            $data['current_status'] = 3;
            $data['description'] = "已验收完毕。感谢您在帮米购物，欢迎再次光临。";
            InstallTracks::updateInstallStatus($order_main_id, $data);


            $option['related_id'] = $order->id;
            app('notification')->send(
                $order->uid, // 用户ID
                Template::ORDER,
                Template::ORDER_TYPE[1],
                getNoticeTitle(1,"COMPLETED"),
                ['order_no' => $order->order_sn],
                $option

            );

            return true;
        } catch (\Exception $e) {
            throw new AppException("确认验收失败");
        }


    }

    public function getAddress(): array
    {
        $member_id = \YunShop::app()->getMemberId();

        // 获取当前用户的所有主订单，包含对应 project_id
        $orders = Order::where('uid', $member_id)
            ->where('parent_id', 0)
            ->where('order_type', 1)
            ->select('id', 'project_id')
            ->get();

        // 提取订单 ID 和项目 ID
        $orderIds = $orders->pluck('id')->toArray();
        $projectMap = $orders->pluck('project_id', 'id'); // [order_id => project_id]

        // 获取项目名称映射 [project_id => name]
        $projectList = Project::whereIn('id', $projectMap->values()->unique())
            ->pluck('name', 'id');

        // 获取地址信息
        $addressList = OrderAddress::whereIn('order_main_id', $orderIds)
            ->select('order_main_id', 'address', 'mobile', 'realname', 'province_id', 'city_id', 'district_id')
            ->get()
            ->unique('address'); // address 字段去重

        // 组装结果
        $results = $addressList->map(function ($item) use ($projectMap, $projectList) {
            $address_map = explode(" ", $item->address);
            $projectId = $projectMap[$item->order_main_id] ?? null;
            return [
                'province' => $address_map[0] ?? '',
                'city' => $address_map[1] ?? '',
                'district' => $address_map[2] ?? '',
                'address' => implode(" ", array_slice($address_map, 3)),
                'phone' => $item->mobile,
                'realname' => $item->realname,
                'province_id' => $item->province_id,
                'city_id' => $item->city_id,
                'district_id' => $item->district_id,
                'project_name' => $projectList[$projectId] ?? '',
            ];
        });

        return $results->values()->toArray();
    }


    public function submitOrderSuccess(int $order_id): array
    {
        $order = Order::with(['project' => function ($query) {
            $query->select("id", "name");
        }])->find($order_id);
        if (!$order) {
            throw new AppException("订单不存在");
        }
        //获取订单收货信息
        $order_address = OrderAddress::where('order_main_id', $order->id)->first();
        return [
            'id' => $order->id,
            "order_sn" => $order->order_sn,
            "goods_total" => $order->goods_total,
            "goods_price" => $order->goods_price,
            "project_name" => $order->project->name,
            "address" => $order_address->address,
            "consignee" => $order_address->realname . " " . $order_address->mobile

        ];
    }

    public function getPickupPoint(int $order_id): array
    {
        $data = Order::where('parent_id', $order_id)
            ->with(['supplier' => function ($query) {
                $query->select('id', 'store_name', 'customer_phone', 'mobile');
            }])
            ->get();

        // 获取订单数量
        $count = $data->count();

// 遍历处理数据
        $result = $data->map(function ($order) {
            $supplier = $order->supplier;

            // 查询提货地址
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
                'service_link' => ServiceUser::getDistributeService($supplier->id),
                'store_name' => $supplier->store_name
            ];
        });

// 返回处理后的结果和数量
        return [
            'count' => $count,
            'point_data' => $result
        ];


    }


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

        //生成物流单号
        if (!$order->logistics_order_sn) {
            $logisticOrderSn = generateLogisticsNumber();
            $order->logistics_order_sn = $logisticOrderSn;
            $order->save();
        } else {
            $logisticOrderSn = $order->logistics_order_sn;
        }

        //提货点
        $returnAddresses = \app\backend\modules\goods\models\ReturnAddress::where('supplier_id', $order->supp_id)->where('is_refund', 2)->where('is_default', 1)->first();
        $receiv_address = OrderAddress::where('order_id', $order_id)->first();
        //获取物流供应商
        $supplierLogistic = SupplierLogisticPrice::where('order_id', $order->parent_id)->where('bidding_status', 4)->first();
        //$supplier = Supplier::where('id', Session::get('supplier')['id'])->first();
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
                "logistics_company_name" => "",
                "remark1" => "1、司机必须凭此文件提货，如果没有携带不得提货。",
                "remark2" => "2、司机签字前，以确认以上所提所有包装信息正确无误。",
                "remark3" => "3、货物供应商应在自提货后2小时内在平台上传货物信息。",
                "remark4" => "4、物流供应商应在自提货后24小时内在平台上传揽收凭证。",
            ]
        ];
        return $data;
    }


}