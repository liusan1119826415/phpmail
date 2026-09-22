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

class BidOrderController extends BaseController
{

    /**
     * 页码
     */
    const PAGE_SIZE = 20;


    public function getData()
    {
        if (request()->ajax()) {
            $params = \YunShop::request()->get('search', []);




            // 查询订单
            $query = VueOrder::whereIn('order_type', [2, 3, 4])
                ->with([
                    'project' => function ($query) {
                        $query->select("id", "name", "province_id", "city_id", "district_id", "contact_name", "phone");
                    },
                    'projectBid'=>function($query){
                        $query->select("id","consignee","consignee_mobile","consignee_address_detail","consignee_province_id","consignee_city_id","consignee_district_id");
                    }
                ]);

            $bid_id = request()->input('bid_id');
            if($bid_id){
                $query->where('bid_id',$bid_id);
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

            if($params['status']){
                $query->where('status',$this->getStatus($params['status']));
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

            if (array_get($params, 'pay_type', '')) {
                $query->where('pay_type_id', $params['pay_type']);
            }

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

            // 预加载地址数据，减少SQL查询
            $provinceIds = $data->pluck('project.province_id')->filter()->unique()->toArray();
            $cityIds = $data->pluck('project.city_id')->filter()->unique()->toArray();
            $districtIds = $data->pluck('project.district_id')->filter()->unique()->toArray();

            $consignee_province_id = $data->pluck('projectBid.consignee_province_id')->filter()->unique()->toArray();

            $consignee_city_id = $data->pluck('projectBid.consignee_city_id')->filter()->unique()->toArray();

            $consignee_district_id = $data->pluck('projectBid.consignee_district_id')->filter()->unique()->toArray();
            $allIds = array_merge($provinceIds, $cityIds, $districtIds,$consignee_province_id,$consignee_city_id,$consignee_district_id);

            $addressMap = Address::whereIn('id', $allIds)->pluck('areaname', 'id');

            // 转换数据
            $data->transform(function ($order) use ($addressMap) {
                if ($order->project) {
                    $order->project->full_address = implode(' ', array_filter([
                        $addressMap[$order->project->province_id] ?? '',
                        $addressMap[$order->project->city_id] ?? '',
                        $addressMap[$order->project->district_id] ?? '',
                        $order->project->address_detail
                    ]));

                }
                if($order->order_type == 2){
                    $order->address_detail = implode(' ', array_filter([
                        $addressMap[$order->projectBid->consignee_province_id] ?? '',
                        $addressMap[$order->projectBid->consignee_city_id] ?? '',
                        $addressMap[$order->projectBid->consignee_district_id] ?? '',
                        $order->projectBid->consignee_address_detail
                    ]));
                }
                if(in_array($order->status,[4,0,-1])) {
                    $order->belongsToMember->mobile = maskPhoneNumber($order->belongsToMember->mobile);
                }
                return $order;
            });

            $total_price = $data->sum('price');
            $data = $data->toArray();
            $data['total_price'] = $total_price;
            $data['expressCompanies'] = ExpressCompany::create()->all();
            return $this->successJson('ok',$data);
        }


    }



    private function getStatus($status)
    {
        $arr = [
            1 => 0,
            2 => 3,
            3 => -1,
        ];
        return $arr[$status];
    }

    /**
     * @return string
     * @throws \Throwable
     */
    public function index()
    {
        return view('bid_order.list', [])->render();
    }


    public function detail()
    {
        $id = request()->id;
        if(request()->ajax()){
            $detail = VueOrder::with([
                'belongsToMember' => function($query){
                    return $query->select(['uid', 'mobile', 'nickname', 'realname','avatar','idcard', 'email']);
                },
                'project'=>function($query){
                    $query->select("id","name");
                },


            ])->find($id);

            return $this->successJson('ok',$detail);
        }

        return view('bid_order.detail', ['id'=>$id])->render();
    }




}
