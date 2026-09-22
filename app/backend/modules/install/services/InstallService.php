<?php


namespace app\backend\modules\install\services;


use app\backend\modules\industry\models\CaseLable;
use app\backend\modules\order\models\Order;
use app\backend\modules\order\models\VueOrder;
use app\common\exceptions\ShopException;
use app\common\models\goods\GoodsOptionModel;
use app\common\models\Member;
use app\common\models\OrderAddress;
use app\common\models\project\InstallOrder;
use app\common\services\Session;
use app\framework\Http\Request;
use Yunshop\Supplier\common\models\Supplier;
use Yunshop\Supplier\common\models\SupplierInstallPrice;
use Yunshop\Supplier\common\models\SupplierLogisticPrice;
use Yunshop\Supplier\common\models\SupplierOrderReads;
use Carbon\Carbon;
use Yunshop\Supplier\common\models\SupplierColorPlane;
class InstallService
{


    public function getList($search)
    {

        $query = VueOrder::where('parent_id',0)->with(['supplierInstallPrice','installOrder'])->where('order_type',1)/*->where('choose_install',1)*/->whereIn('status',[1,2])->whereIn('orderStatus',[1,2,3]);


        request()->merge(['mtype' => 1]);
        if($search['dispense_status']){

            $query->where('install_dispense_status',$search['dispense_status']);

        }

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
             $query->whereHas('supplierInstallPrice',function ($query) use ($search){
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

            $order->goods_total_num = $order->hasManyChildren->sum('goods_total');
            $order->project_name = $order->project->name;
            //计算

            $order->timeLeftStr =  $this->getLeadTime($order->id)['timeLeftStr'];

            if($order->supplierInstallPrice){
                $order->min_install_price = $order->supplierInstallPrice->where('bidding_status', '>=', 2)->min('freight_price');
                $order->install_num = $order->supplierInstallPrice->where('bidding_status', '>=', 2)->count();
                $hasBiddingStatus2OrMore = $order->supplierInstallPrice->where('bidding_status', '>=', 2)->isNotEmpty();
                $order->install_status_flag = $hasBiddingStatus2OrMore ? 1 : 0;

            }else{
                $order->install_status_flag = 0;
            }
            $order->install_sign_certificate = $order->install_sign_certificate?$absolute_paths = array_map(function ($path) {
                return yz_tomedia($path);
            }, unserialize($order->install_sign_certificate)):[];


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



    public function getGoods($id)
    {
        $orderIds = Order::where('parent_id',$id)->pluck('id')->toArray();
        $goods_total_num = Order::whereIn('id',$orderIds)->sum('goods_total');
        $query = \app\common\models\OrderGoods::where('parent_id',0)->with(['floors:id,name', 'spaces:id,name','order'=>function($query){
            $query->select("id","order_sn","supp_id")->with(['supplier'=>function($query){
                $query->select("id","store_name");
            }]);
        },'orderGoodsChildrens'=>function($query){
            $query->with(['goodsOption']);
        }])->where('refund_success',0);
        if($orderIds){
            $query->whereIn('order_id', $orderIds);
        }


        $OrderGoods = $query->get()
            ->groupBy(fn($item) => $item->floors->name ?? '未分配楼层')
            ->map(function ($floorGroup, $floorName){
                return [
                    'id' => $floorGroup->first()->floor_id ?? 0, // 取第一条数据的 floor_id
                    'name' => $floorName,
                    'data' => $floorGroup->groupBy(fn($item) => $item->spaces->name ?? '未分配空间')
                        ->map(function ($spaceGroup, $spaceName){
                            return [
                                'id' => $spaceGroup->first()->space_id ?? 0, // 取第一条数据的 space_id
                                'name' => $spaceName,
                                'data' => $spaceGroup->map(function ($item){
                                    // 给$item增加客服链接

                                    $item->material_color = $this->getMaterialColor($item->toArray());
                                    return $item; // 返回完整的$item对象
                                })->values()->all(),
                            ];
                        })->values()->all(),
                ];
            })->values()->all();

        $data['order_goods'] = $OrderGoods;
        $receiv_address = OrderAddress::where('order_main_id', $id)->first();
        $data['receiv_address'] = $receiv_address->address;
        $data['contact_info'] = $receiv_address->realname. "/". $receiv_address->mobile;
        $data['goods_total_num'] = $goods_total_num;
        return $data;

    }


    protected function getMaterialColor($item)
    {
        if($item['components']){
            if($item['type'] == 1){
                $data = json_decode($item['components'],true);
                $colorIds = array_column($data, 'color_id');
                $componentIds = array_column($data, 'component_id');


                $colors = SupplierColorPlane::whereIn('id', $colorIds)->pluck('name', 'id');

                $components = GoodsOptionModel::whereIn('id', $componentIds)->pluck('name', 'id');

                $result = [];

                foreach ($data as $item) {
                    $color = $colors[$item['color_id']] ?? '未知颜色';
                    $component = $components[$item['component_id']] ?? '未知部件';

                    $result[] = "{$color}/{$component}";
                }
                $finalResult = implode('|', $result);
            }else{
                $finalResult = $item['components'];
            }
        }else{
            $finalResult = "";
        }
        return $finalResult;
    }


    public function publishOrder($order_id,$data): bool
    {
        try {
            $supplierList = Supplier::where('role_id', 3)->select("id", "store_name")->get();

            //安装order
            $InstallOrder = InstallOrder::where('order_id',$order_id)->first();
            if(!$InstallOrder){
                InstallOrder::create(
                    [
                        'order_id'=>$order_id,
                        'install_days'=>$data['install_days'],
                        'floor'=>$data['floor'],
                        'has_elevator'=>$data['has_elevator'],
                        'remark'=>$data['remark'],
                        'install_publish_time'=>time(),
                    ]
                );
            }else{
                $InstallOrder->install_days = $data['install_days'];
                $InstallOrder->floor = $data['floor'];
                $InstallOrder->has_elevator = $data['has_elevator'];
                $InstallOrder->remark = $data['remark'];
                $InstallOrder->save();

            }


            if ($supplierList->isNotEmpty()) {
                $data = [];
                $orderReads = [];

                foreach ($supplierList as $item) {
                    // 判断是否已经存在于 SupplierLogisticPrice
                    $existsLogistic = SupplierInstallPrice::where('supplier_id', $item->id)
                        ->where('order_id', $order_id)
                        ->exists();

                    if (!$existsLogistic) {
                        $data[] = [
                            "supplier_id" => $item->id,
                            "store_name" => $item->store_name,
                            "order_id" => $order_id,
                            "created_at" => time()
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
                    SupplierInstallPrice::insert($data);
                }

                if (!empty($orderReads)) {
                    SupplierOrderReads::insert($orderReads);
                }
            }



            $order = Order::find($order_id);
            $order->install_dispense_status = 2;
            $order->save();



            return true;
        } catch (\Exception $e) {
            throw new ShopException($e->getMessage());
        }
    }



}