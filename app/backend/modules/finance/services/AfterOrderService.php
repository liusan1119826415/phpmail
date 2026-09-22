<?php


namespace app\backend\modules\finance\services;


use app\backend\modules\industry\models\CaseLable;
use app\backend\modules\order\models\Order;
use app\backend\modules\order\models\VueOrder;
use app\common\exceptions\ShopException;
use app\common\models\Member;
use app\common\services\Session;
use Yunshop\Supplier\common\models\Supplier;
use Yunshop\Supplier\common\models\SupplierLogisticPrice;
use Yunshop\Supplier\common\models\SupplierOrderReads;
use Carbon\Carbon;
class AfterOrderService
{


    public function getList($search)
    {

        $query = VueOrder::where('parent_id',0)->with(['supplierPrice'])->where('order_type',1)->whereIn('status',[1,2])->whereIn('orderStatus',[1,2,3]);
        if($search['dispense_status']){
            $query->where('dispense_status',$search['dispense_status']);
        }
        request()->merge(['mtype' => 2]);
        if ($search['ambiguous']['field'] == "member" && $search['ambiguous']['string']) {

            list($field, $value) = explode(':', $search['ambiguous']['string']);
            if (isset($value)) {
                return $query->where($field, $value);
            } else {
                //todo wherein这个可能有个数限制，用连表模糊匹配查询又用不了索引，导致有数据多的客户老是查询超时，先优化成这个样子，少用全表扫描的查询！
                $memberIds = Member::uniacid()
                    ->whereRaw('LOCATE(?, realname)', [$search['ambiguous']['string']])
                    ->orWhereRaw('LOCATE(?, mobile)', [$search['ambiguous']['string']])
                    ->orWhereRaw('LOCATE(?, nickname)', [$search['ambiguous']['string']])
                    ->pluck('uid')->all();
                $query->whereIn('uid', ($memberIds ?: [0]));
            }
        }

        if ($search['ambiguous']['field'] == "project" && $search['ambiguous']['string']) {

            $query->whereHas('project',function ($query) use ($search){
                $query->where('name','like','%'.$search['ambiguous']['string'].'%');
            });
        }

        if ($search['ambiguous']['field'] == "company_name" && $search['ambiguous']['string']) {
             $query->whereHas('supplierPrice',function ($query) use ($search){
                 $query->where('store_name','like','%'.$search['ambiguous']['string'].'%');
             });
        }


        if ($search['ambiguous']['field'] == 'order' && $search['ambiguous']['string']) {

            if (strpos($search['ambiguous']['string'], 'PN') === 0) {
                $query->whereHas('hasOneOrderPay', function ($query) use ($search) {
                    $query->where('pay_sn', $search['ambiguous']['string']);
                });
            } else {
                $query->where('order_sn', $search['ambiguous']['string']);
            }
        }

        if ($search['start_time'] && $search['end_time'] && $search['time_field']) {
            $range = [strtotime($search['start_time']), strtotime($search['end_time'])];
            $query->whereBetween( $search['time_field'], $range);
        }

        $list = $query->orderBy('create_time', 'desc')->paginate(15);
        $list->transform(function ($order) {

            $order->order_address = $order->hasManyChildren->first()->address;
            $order->project_name = $order->project->name;


            //计算

            $order->timeLeftStr =  $this->getLeadTime($order->id)['timeLeftStr'];
            $order->advanceStr  = $this->getLeadTime($order->id)['advanceStr'];

            if($order->supplierPrice){
                $order->min_freight_price = $order->supplierPrice->min('freight_price');
                $order->logistics_num = $order->supplierPrice->count();
            }


            return $order;
        });

        return $list;




    }


    public function getLeadTime($id)
    {
        $parentOrder = Order::find($id);


        // 获取所有子订单和商品信息
        $orderList = Order::where('parent_id', $id)
            ->with(['orderGoods.goods'])
            ->get();

        $latestShipDate = null;

        foreach ($orderList as $item) {
            $maxLeadTime = 0;

            foreach ($item->orderGoods as $orderGood) {
                if ($orderGood->goods && $orderGood->goods->lead_time) {
                    $leadTime = (int)$orderGood->goods->lead_time;
                    $maxLeadTime = max($maxLeadTime, $leadTime);
                }
            }

            // 当前子订单的预计发货时间
            $expectedShipDate = Carbon::parse($parentOrder->first_pay_time)->addDays($maxLeadTime);

            // 比较找出最晚的预计发货时间
            if (is_null($latestShipDate) || $expectedShipDate->gt($latestShipDate)) {
                $latestShipDate = $expectedShipDate;
            }
        }


        // 获取时间差（精确到分）
        $now = Carbon::now();
        $diff = $now->diff($latestShipDate);

        $timeLeftStr = sprintf(
            '%s%d天 %d小时 %d分钟',
            $latestShipDate->lessThan($now) ? '已超时 ' : '',
            $diff->d,
            $diff->h,
            $diff->i
        );


        // ✅ 提前2天的时间点
        $advanceDate = $latestShipDate->copy()->subDays(2);
        $advanceDiff = $now->diff($advanceDate);
        $advanceStr = sprintf(
            '%s%d天 %d小时 %d分钟',
            $advanceDate->lessThan($now) ? '已超时 ' : '',
            $advanceDiff->d,
            $advanceDiff->h,
            $advanceDiff->i
        );

        return ['advanceStr'=>$advanceStr,'timeLeftStr'=>$timeLeftStr];


    }


    public function publishOrder($order_id): bool
    {
        try {
            $supplierList = Supplier::where('role_id', 2)->select("id", "store_name")->get();
            if ($supplierList->isNotEmpty()) {
                $data = [];
                $orderReads = [];

                foreach ($supplierList as $item) {
                    // 判断是否已经存在于 SupplierLogisticPrice
                    $existsLogistic = SupplierLogisticPrice::where('supplier_id', $item->id)
                        ->where('order_id', $order_id)
                        ->exists();

                    if (!$existsLogistic) {
                        $data[] = [
                            "supplier_id" => $item->id,
                            "store_name" => $item->store_name,
                            "order_id" => $order_id,
                            "created_at" => time(),
                            "publish_time"=>time(),
                        ];
                    }

                    // 判断是否已经存在于 SupplierOrderReads
                    $existsRead = SupplierOrderReads::where('supplier_id', $item->id)
                        ->where('order_id', $order_id)->where('type',1)
                        ->exists();

                    if (!$existsRead) {
                        $orderReads[] = [
                            "supplier_id" => $item->id,
                            "order_id" => $order_id,
                            "type" => 1,
                            "created_at" => time()
                        ];
                    }
                }

                // 批量插入不重复数据
                if (!empty($data)) {
                    SupplierLogisticPrice::insert($data);
                }

                if (!empty($orderReads)) {
                    SupplierOrderReads::insert($orderReads);
                }
            }

            $order = Order::find($order_id);
            $order->dispense_status = 2;
            $order->publish_time = time();
            $order->save();

            return true;
        } catch (\Exception $e) {
            throw new ShopException($e->getMessage());
        }
    }


    public function edit($StyleModel,$data):bool
    {

        if($data) {
            //将数据赋值到model
            $StyleModel->setRawAttributes($data);
            //字段检测
            $validator = $StyleModel->validator($StyleModel->getAttributes());
            if ($validator->fails()) {//检测失败
                throw new ShopException($validator->messages());
            } else {
                //数据保存
                if ($StyleModel->save()) {
                    //显示信息并跳转
                    return true;
                }else{
                    throw new ShopException('修改成功');
                }
            }
        }
    }





    public function delete($id):bool
    {
        try {
            $style = CaseLable::find($id);
            if(!$style) {
                throw new ShopException('无此Lable或已经删除');
            }
            $style->delete();
        }catch (\Exception $e){
            throw new ShopException('删除失败');
        }
        return true;
    }
}