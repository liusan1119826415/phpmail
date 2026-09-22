<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2022/10/20
 * Time: 16:18
 */

namespace app\backend\modules\refund\models;


use app\backend\modules\order\models\VueOrder;
use app\common\models\Member;
use app\common\models\OrderGoods;
use app\common\models\refund\RefundGoodsLog;
use app\framework\Database\Eloquent\Builder;

/**
 * Class OrderRefund
 * @method static self backendSearch($search)
 * @package app\backend\modules\refund\models
 */
class OrderRefund extends \app\common\models\refund\RefundApply
{
    protected $appends = [
        'plugin_id','refund_type_name', 'status_name',
        'receive_status_name', 'refund_way_type_name', 'order_type_name','project_name'
    ];



    public function getProjectNameAttribute()
    {
        return $this->order->project->name;
    }

    public function getOrderTypeNameAttribute()
    {
        return $this->order->getOrderType()->getName();
    }

    public function getBackendButtonModels()
    {
        return (new \app\backend\modules\refund\services\BackendRefundButtonService($this))->getButtonModels();
    }

    public function getBackendRefundSteps()
    {
        return (new \app\backend\modules\refund\services\steps\RefundStatusStepManager($this))->getStepItems();
    }

    public static function detail($id)
    {
        return self::with([
            'hasOneMember' => function ($member) {
                $member->select(['uid', 'avatar', 'nickname', 'realname', 'mobile', 'createtime',
                    'credit1', 'credit2',]);
            },
            'order'=>function($query){
               $query->with(['address','hasOneOrderPay']);
            },
            'refundOrderGoods',
            'refundPayRecord',
            'processLog',
            'returnExpress',
            'hasManyResendExpress',
            'hasManyReturnExpress',
            'changeLog',
        ])->find($id);
    }


    public function scopeBackendSearch(Builder $query, $search = [])
    {


        $model = $query->select('yz_order_refund.*');


        if($search['is_bid'] == 2){
            $model->leftJoin('yz_order', 'yz_order_refund.order_id', 'yz_order.id')
                ->whereIn('yz_order.order_type',  [2,3,4]);

            // 当 order_type == 2 时，再关联另一张表
            $model->leftJoin('yz_project_bid', function($join) {
                $join->on('yz_order.bid_id', '=', 'yz_project_bid.id')
                    ->where('yz_order.order_type', '=', 2);
            });

            // 如果需要查询另一张表的字段
            $model->addSelect('yz_project_bid.*');

        }

        if($search['is_bid'] == 1){
            $model->leftJoin('yz_order', 'yz_order_refund.order_id', 'yz_order.id')
                ->where('yz_order.order_type',  1);

            if(request()->is_platform == 1){

                $model->where('yz_order.parent_id',0);
                $model->leftJoin('yz_order_address', 'yz_order_refund.order_id', 'yz_order_address.order_main_id');

                // 3. 最后选择要查询的字段
                $model->addSelect([
                    'yz_order_address.id as aid',
                    'yz_order_address.order_main_id',
                    'yz_order_address.address',
                    'yz_order_address.mobile',
                    'yz_order_address.realname',

                ]);
            }
        }

        if($search['is_bid'] == 3) {
            // 1. 先关联yz_order表
            $model->leftJoin('yz_order', 'yz_order_refund.order_id', 'yz_order.id')
                ->where('yz_order.order_type', 5);

            // 2. 再关联yz_project_door表
            $model->leftJoin('yz_project_door', function($join) {
                $join->on('yz_order.door_id', '=', 'yz_project_door.id');
            });

            // 3. 最后选择要查询的字段
            $model->addSelect([
                'yz_project_door.id as did',
                'yz_project_door.service_id',
                'yz_project_door.service_type',
                'yz_project_door.service_day',
                'yz_project_door.contact_name',
                'yz_project_door.contact_phone'
            ]);

        }



        if($search['ambiguous']['field'] == 1 && $search['ambiguous']['string']){
            //项目名称搜索

            $model->leftJoin('yz_my_project', 'yz_order.project_id', 'yz_my_project.id')
                ->where('yz_my_project.name', 'like', '%'.trim($search['ambiguous']['string']).'%');

        }

        if($search['ambiguous']['field'] == 2 && $search['ambiguous']['string']){
            //用户手机号
            list($field, $value) = explode(':', $search['ambiguous']['string']);
            if (isset($value)) {
                $model->where($field, $value);
            } else {
                //todo wherein这个可能有个数限制，用连表模糊匹配查询又用不了索引，导致有数据多的客户老是查询超时，先优化成这个样子，少用全表扫描的查询！
                $memberIds = Member::uniacid()
                    ->whereRaw('LOCATE(?, realname)', [$search['ambiguous']['string']])
                    ->orWhereRaw('LOCATE(?, mobile)', [$search['ambiguous']['string']])
                    ->orWhereRaw('LOCATE(?, nickname)', [$search['ambiguous']['string']])
                    ->pluck('uid')->all();
                $model->leftJoin('yz_order', 'yz_order_refund.order_id', 'yz_order.id')->whereIn('yz_order.uid', ($memberIds ?: [0]));
            }
        }

        if($search['ambiguous']['field'] == 3 && $search['ambiguous']['string']){
            //订单号
            $model->where('yz_order.order_sn',  trim($search['ambiguous']['string']));

        }

        if($search['ambiguous']['field'] == 4 && $search['ambiguous']['string']){
            //售后单号
            $model->where('yz_order_refund.refund_sn', trim($search['ambiguous']['string']));
        }
        if ($search['order_sn']) {

            $model->where('yz_order.order_sn',  trim($search['order_sn']));

        }

        if ($search['refund_sn']) {
            $model->where('yz_order_refund.refund_sn', trim($search['refund_sn']));

        }

        if (isset($search['refund_type']) && is_numeric($search['refund_type'])) {
            $model->where('refund_type', $search['refund_type']);
        }

        if (isset($search['status']) && is_numeric($search['status'])) {

            if ($search['status'] == 99) {
                $model->whereIn('status', [self::COMPLETE,self::CONSENSUS,self::CLOSE]);
            } else {
                $model->where('status', $search['status']);
            }
        }


        if ($search['member_id']) {
            $model->where('yz_order_refund.uid', $search['member_id']);
        }

        if (!empty($search['member_info']) && isset($search['member_type'])) {

            $model->whereHas('hasOneMember', function ($member) use ($search) {
                $member->select('uid', 'nickname', 'realname', 'mobile', 'avatar')
                    ->where(function ($query) use ($search) {
                        switch ($search['member_type']) {
                            case 1 :
                                $query->where('nickname', 'like', '%' . $search['member_info'] . '%');
                                break;
                            case 2 :
                                $query->where('realname', 'like', '%' . $search['member_info'] . '%');
                                break;
                            case 3 :
                                $query->where('mobile', 'like', '%' . $search['member_info'] . '%');
                                break;
                            default :
                        }
                    });
            });
        }

        //商品id  商品名称
        if ($search['goods_id'] || $search['goods_title']) {
            $orderGoodsModel = OrderGoods::uniacid();
            if ($search['goods_id']) {
                $orderGoodsModel->where('goods_id', $search['goods_id']);
            }

            if ($search['goods_title']) {
                $orderGoodsModel->where('title', 'like', "%".trim($search['goods_title'])."%");
            }

            $order_ids = $orderGoodsModel->pluck('order_id')->unique()->toArray();

            $model->whereIn('yz_order_refund.order_id', $order_ids);
        }



        //操作时间范围
        if ($search['start_time'] && $search['end_time'] && $search['time_field']) {
            $range = [strtotime($search['start_time']), strtotime($search['end_time'])];

            $model->whereBetween('yz_order_refund.'.$search['time_field'], $range);
        }


        $model->with([
            'hasOneMember' => function ($member) {
                $member->select(['uid', 'avatar', 'nickname', 'realname', 'mobile', 'createtime',
                    'credit1', 'credit2',]);
            },
            'order' => function ($order) {
                $order->with(['address']);
            },
            'refundOrderGoods',
        ]);



        return $model;
    }

    public function order()
    {
        return $this->belongsTo(VueOrder::class, 'order_id', 'id');
    }
}