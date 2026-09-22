<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/12/1
 * Time: 9:56
 */

namespace app\backend\modules\order\controllers;


use app\backend\modules\charts\models\Order;
use app\common\components\BaseController;

use app\common\models\Address;
use app\common\models\goods\GoodsOptionModel;
use app\common\models\Member;

use app\common\repositories\ExpressCompany;
use app\common\services\Session;
use Yunshop\GoodsSource\common\models\GoodsSet;
use Yunshop\GoodsSource\common\models\GoodsSource;

use Yunshop\StoreCashier\common\models\StoreDelivery;

use Yunshop\TeamDividend\models\TeamDividendLevelModel;
use app\backend\modules\order\models\VueOrder;

use Yunshop\Supplier\common\models\SupplierColorPlane;
use Yunshop\Supplier\common\models\SupplierService;
use Yunshop\Supplier\common\models\SupplierServiceFee;
class DoorOrderController extends BaseController
{

    /**
     * 页码
     */
    const PAGE_SIZE = 20;


    public function getData()
    {
        if (request()->ajax()) {
            $params = \YunShop::request()->get('search', []);

            // 处理模糊搜索字段
            if ($params) {
                $params = array_filter($params, function ($item) {
                    return !empty($item);
                });
            }

            // 查询订单

            $query = Order::where('order_type', 5)
                ->with([
                    'project' => function ($query) {
                        $query->select("id", "name");
                    },
                    'projectDoor' => function ($query) {
                        $query->select('id', 'service_id', 'service_type', 'project_area', 'province_id', 'city_id', 'district_id');
                    }
                ]);
            if ($params['status']) {

                $query->where('status', $this->getStatus($params['status']));
            }

            if ($params['ambiguous']['field'] == "member" && $params['ambiguous']['string']) {

                list($field, $value) = explode(':', $params['ambiguous']['string']);
                if (isset($value)) {
                    return $query->where($field, $value);
                } else {
                    //todo wherein这个可能有个数限制，用连表模糊匹配查询又用不了索引，导致有数据多的客户老是查询超时，先优化成这个样子，少用全表扫描的查询！
                    $memberIds = Member::uniacid()
                        ->whereRaw('LOCATE(?, realname)', [$params['ambiguous']['string']])
                        ->orWhereRaw('LOCATE(?, mobile)', [$params['ambiguous']['string']])
                        ->orWhereRaw('LOCATE(?, nickname)', [$params['ambiguous']['string']])
                        ->pluck('uid')->all();
                    $query->whereIn('uid', ($memberIds ?: [0]));
                }
            }

            if ($params['ambiguous']['field'] == 'order' && $params['ambiguous']['string']) {

                if (strpos($params['ambiguous']['string'], 'PN') === 0) {
                    $query->whereHas('hasOneOrderPay', function ($query) use ($params) {
                        $query->where('pay_sn', $params['ambiguous']['string']);
                    });
                } else {
                    $query->where('order_sn', $params['ambiguous']['string']);
                }
            }

            //支付方式
            if (array_get($params, 'pay_type', '')) {
                $query->where('pay_type_id', $params['pay_type']);
            }

            //操作时间范围


            if ($params['start_time'] && $params['end_time'] && $params['time_field']) {
                $range = [strtotime($params['start_time']), strtotime($params['end_time'])];

                $query->whereBetween('yz_order.' . $params['time_field'], $range);
            }
            //时间范围搜索 #33625
            if (array_get($params, 'time_range.field', '') && array_get($params, 'time_range.start', 0) && array_get($params, 'time_range.end', 0)) {
                $range = [strtotime($params['time_range']['start']), strtotime($params['time_range']['end'])];
                $query->whereBetween($params['time_range']['field'], $range);
            }


            $data = $query->orderBy('create_time', 'desc')->paginate(20);
            $data->total_price = $data->sum('price');
            // 预加载地址数据，减少SQL查询
            $provinceIds = $data->pluck('projectDoor.province_id')->filter()->unique()->toArray();
            $cityIds = $data->pluck('projectDoor.city_id')->filter()->unique()->toArray();
            $districtIds = $data->pluck('projectDoor.district_id')->filter()->unique()->toArray();
            $allIds = array_merge($provinceIds, $cityIds, $districtIds);

            $addressMap = Address::whereIn('id', $allIds)->pluck('areaname', 'id');

            // 转换数据

            $service_ids = $data->pluck('projectDoor.service_id')->unique()->filter()->toArray();

            $supplier_service = SupplierService::withTrashed()->whereIn('id', $service_ids)->with(['service'])->get();

            $supplier_service_map = $supplier_service->keyBy('id');

            $service_type_ids = $data->pluck('projectDoor.service_type')
                ->flatMap(function ($type) {
                    return explode(',', $type); // 拆分字符串
                })
                ->unique()
                ->filter()
                ->values()
                ->toArray();

            $supplier_service_fee = SupplierServiceFee::withTrashed()->whereIn('id', $service_type_ids)->with(['service'])->get();

            $service_fee_map = $supplier_service_fee->keyBy('id');
            $projectIds = $data->pluck('project_id')->unique()->filter()->toArray();
            $payData = Order::whereIn('project_id', $projectIds)->where('order_type', 3)->whereIn('status', [1, 2, 3])->get()->keyBy('project_id');

            $data->transform(function ($order) use ($addressMap, $service_fee_map, $supplier_service_map, $payData) {

                $order->full_address = implode(' ', array_filter([
                    $addressMap[$order->projectDoor->province_id] ?? '',
                    $addressMap[$order->projectDoor->city_id] ?? '',
                    $addressMap[$order->projectDoor->district_id] ?? '',
                    $order->projectDoor->address_detail
                ]));

                $order->service_name = optional($supplier_service_map[$order->projectDoor->service_id]->service ?? null)->name ?? '';
                $order->is_brand_pay = !empty($payData[$order->project_id]) ? 1 : 0;
                $service_type_ids = explode(',', $order->projectDoor->service_type);
                $order->service_fee_list = collect($service_type_ids)
                    ->map(function ($id) use ($service_fee_map) {
                        return optional($service_fee_map[$id]->service ?? null)->name ?? '';
                    })
                    ->filter()
                    ->values()
                    ->toArray();

                return $order;
            });
            $total_price = $data->sum('price');
            $data = $data->toArray();
            $data['total_price'] = $total_price;
            $data['expressCompanies'] = ExpressCompany::create()->all();
            return $this->successJson('ok', $data);
        }


    }



    private function getStatus($status)
    {
        $arr = [
            1 => 4,
            2 => 0,
            3 => 3,
        ];
        return $arr[$status];
    }

    /**
     * @return string
     * @throws \Throwable
     */
    public function index()
    {
        return view('door_order.list', [])->render();
    }


    public function detail()
    {
        $id = request()->input('id');
        if (request()->ajax()) {

            $this->validate([
                'id' => 'required|integer|min:0',
            ]);
            $detail = Order::where('id', $id)
                ->with([
                    'belongsToMember'=>function($query){
                        $query->select("uid","nickname","mobile","avatar");
                    },
                    'projectDoor'
                ])->first();

            $address_map = Address::whereIn('id',[$detail->projectDoor->province_id,$detail->projectDoor->city_id,$detail->projectDoor->district_id])->pluck('areaname')->toArray();
            $supplier_service = SupplierService::withTrashed()->where('id',$detail->projectDoor->service_id)->with(['service'])->first();
            $detail->addressDetail = $address_map[0].$address_map[1].$address_map[2].$detail->projectDoor->address_detail;
            $detail->service_name = $supplier_service->service->name;
            $supplier_service_fee = SupplierServiceFee::withTrashed()->whereIn('id', explode(",",$detail->projectDoor->service_type))->with(['service'])->get();
            $detail->service_fee_list = $supplier_service_fee
                ->pluck('service.name')  // 提取 service 的 name
                ->filter()               // 过滤掉 null 值（如果有些没有关联）
                ->values()               // 重建索引
                ->toArray();             // 转为普通数组

            $detail->scene_img = unserialize($detail->projectDoor->scene_img);
            $detail->build_img = unserialize($detail->projectDoor->build_img);
            $detail->information = yz_tomedia($detail->information);

            return $this->successJson('ok',$detail);
        }

        return view('door_order.detail', ['id'=>$id])->render();
    }




}
