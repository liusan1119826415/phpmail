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

use app\common\models\goods\GoodsOptionModel;
use app\common\models\Member;

use Yunshop\GoodsSource\common\models\GoodsSet;
use Yunshop\GoodsSource\common\models\GoodsSource;

use Yunshop\StoreCashier\common\models\StoreDelivery;

use Yunshop\TeamDividend\models\TeamDividendLevelModel;
use app\backend\modules\order\models\VueOrder;

use Yunshop\Supplier\common\models\SupplierColorPlane;

class PurchaseOrderController extends BaseController
{

    /**
     * 页码
     */
    const PAGE_SIZE = 20;


    public function getData()
    {
        $query = VueOrder::where('order_type', 1)->where('parent_id', 0)->with(['orderPayments','project'=>function($q){
            $q->select("id","name");
        },'myOrderAddress']);
        $params = request()->search;
        if (array_get($params, 'ambiguous.field', '') && array_get($params, 'ambiguous.string', '')) {
            if ($params['ambiguous']['field'] == 'order') {
                if ($params['ambiguous']['field'] == 'order') {

                    if (strpos($params['ambiguous']['string'], 'SN') === 0) {
                        $query->where('order_sn', $params['ambiguous']['string']);
                    }
                }
            }

            if ($params['ambiguous']['field'] == 'member') {
                $memberIds = Member::uniacid()
                    ->whereRaw('LOCATE(?, realname)', [$params['ambiguous']['string']])
                    ->orWhereRaw('LOCATE(?, mobile)', [$params['ambiguous']['string']])
                    ->orWhereRaw('LOCATE(?, nickname)', [$params['ambiguous']['string']])
                    ->pluck('uid')->all();
                $query->whereIn('uid', ($memberIds ?: [0]));
            }
        }

        if(request()->code){
            $query->where($this->getWhereStatus(request()->code));
        }

        if (array_get($params, 'pay_type', '')) {
            $query->where('pay_type_id', $params['pay_type']);
        }

        //操作时间范围
        if ($params['start_time'] && $params['end_time'] && $params['time_field']) {
            $range = [strtotime($params['start_time']), strtotime($params['end_time'])];

            $query->whereBetween($params['time_field'], $range);

        }
        //时间范围搜索 #33625
        if (array_get($params, 'time_range.field', '') && array_get($params, 'time_range.start', 0) && array_get($params, 'time_range.end', 0)) {
            $range = [strtotime($params['time_range']['start']), strtotime($params['time_range']['end'])];
            $query->whereBetween($params['time_range']['field'], $range);
        }

        $total_price = $query->sum('goods_price');
        $order_list = $query->orderBy('create_time','desc')->paginate(self::PAGE_SIZE);

        $order_list->map(function ($order){
           $order->setAppends(['status_name','new_status','fixed_button']);
        });

        $order_list = $order_list->toArray();
        $order_list['total_price'] = $total_price;
        return $this->successJson('ok', $order_list);

    }

    /**
     * @return string
     * @throws \Throwable
     */
    public function index()
    {
        return view('purchase_order.list', [])->render();
    }


    public function detail()
    {
        $id = request()->id;
        if(request()->ajax()){
            $detail = VueOrder::with([
                'belongsToMember' => function($query){
                    return $query->select(['uid', 'mobile', 'nickname', 'realname','avatar','idcard', 'email']);
                },
                'myOrderAddress',
                'project'=>function($query){
                    $query->select("id","name");
                },
                'orderPayments',

            ])->find($id);
            $supplier_data = VueOrder::where('parent_id',$id)->with(['supplier'=>function($query){
                $query->select("id","store_name");
            }])->get()->toArray();
            foreach ($supplier_data as &$item){
                if ($item['new_status'] == 3) {
                    // 获取 has_many_order_goods 里面 goods['lead_time'] 最大值
                    $max_lead_time = 0;

                    if (!empty($item['has_many_order_goods'])) {
                        $max_lead_time = max(array_map(function ($goods) {
                            return isset($goods['goods']['lead_time']) ? (int)$goods['goods']['lead_time'] : 0;
                        }, $item['has_many_order_goods']));
                    }

                    // 先将 pay_time 转换为时间戳
                    $pay_time_timestamp = $item['pay_time'] == "1970-01-01 08:00:00" ? 0 : strtotime($item['pay_time']);

                    // 计算生产完成时间（时间戳 + lead_time * 86400）
                    $production_completion_time = $pay_time_timestamp + ($max_lead_time * 86400);

                    // 计算剩余生产天数
                    $remaining_days = ceil(($production_completion_time - time()) / 86400);
                    $remaining_days = max($remaining_days, 0); // 避免负数
                    // 添加计算结果
                    $item['max_lead_time'] = $max_lead_time;
                    $item['production_completion_time'] = date('Y-m-d', $production_completion_time);
                    $item['remaining_days'] = $remaining_days;
                }

            }
            $detail->supplier_data = $supplier_data;
            return $this->successJson('ok',$detail);
        }

        return view('purchase_order.detail', ['id'=>$id])->render();
    }


    public function getDetailList()
    {
        $order_id = request()->input('order_id');
        $is_refund = request()->input('is_refund');

        $query = \app\common\models\OrderGoods::where('refund_success', 0)->where('parent_id',0)->with(['goodsOption' => function ($query) {
            $query->select("id", "product_model");
        }, 'hasOneGoods' => function ($query) {
            $query->select("id", "sku");
        },'orderGoodsChildrens' => function($query) {
            $query->with(['hasOneGoods']);
        }]);

        if ($order_id) {
            $order = Order::find($order_id);
            $key = $order->parent_id == 0 ? "order_main_id" : "order_id";
            $query->where($key, $order_id);
        }

        if ($is_refund == 1) {
            $query->where('refund_id', '>', 0);
        }

        $OrderGoods = $query->get()
            ->map(function ($item) {
                // 处理主订单商品数据
                $item->material_color = $this->getMaterialColor($item->toArray());
                $item->draw_file = !empty($item->draw_file)
                    ? array_map(function ($value) {
                        return yz_tomedia($value);
                    }, unserialize($item->draw_file))
                    : [];
                $item->upload_time = $item->upload_time ? date("Y-m-d H:i:s", $item->upload_time) : "-";
                $item->confirm_time = $item->confirm_time ? date("Y-m-d H:i:s", $item->confirm_time) : "-";

                // 处理子订单商品数据（如果存在）
                if ($item->orderGoodsChildrens && $item->orderGoodsChildrens->isNotEmpty()) {
                    $item->orderGoodsChildrens = $item->orderGoodsChildrens->map(function ($child) {
                        $child->material_color = $this->getMaterialColor($child->toArray());
                        $child->draw_file = !empty($child->draw_file)
                            ? array_map(function ($value) {
                                return yz_tomedia($value);
                            }, unserialize($child->draw_file))
                            : [];
                        $child->upload_time = $child->upload_time ? date("Y-m-d H:i:s", $child->upload_time) : "-";
                        $child->confirm_time = $child->confirm_time ? date("Y-m-d H:i:s", $child->confirm_time) : "-";
                        return $child;
                    });
                }

                return $item;
            })->values();

        return $this->successJson('ok', $OrderGoods);
    }


    protected function getMaterialColor($item)
    {
        if ($item['components']) {
            if ($item['type'] == 1) {
                $data = json_decode($item['components'], true);
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
            } else {
                $finalResult = $item['components'];
            }
        } else {
            $finalResult = "";
        }
        return $finalResult;
    }


    private function getWhereStatus($status)
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


}
