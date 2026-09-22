<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/4/13
 * Time: 下午2:00
 */

namespace app\frontend\modules\refund\controllers;

use app\common\components\ApiController;
use app\common\exceptions\AppException;
use app\common\models\Address;
use app\common\models\kefu\ServiceUser;
use app\common\models\Order;
use app\common\models\refund\RefundGoodsLog;
use app\common\models\refund\RefundProcessLog;
use app\common\modules\refund\RefundOrderFactory;
use app\frontend\modules\refund\models\RefundApply;
use app\frontend\modules\refund\services\RefundService;
use Carbon\Carbon;

class DetailController extends ApiController
{
    public function index(\Illuminate\Http\Request $request){

        $this->validate([
            'refund_id' => 'required|integer',
        ]);
        $refundApply = RefundApply::detail()->withoutGlobalScope('uid')->find($request->input('refund_id'));

        if(!isset($refundApply)){
            throw new AppException('未找到该退款申请');
        }


        //兼容用户寄回物流显示旧数据
        if ($refundApply->hasManyReturnExpress->isNotEmpty()) {
            $refundApply->hasManyReturnExpress->map(function ($returnExpress) use ($refundApply) {
                if (!$returnExpress->address && $refundApply->refund_address) {
                    $returnAddress = \app\common\models\goods\ReturnAddress::where('id', $refundApply->refund_address)->first();
                    if ($returnAddress) {
                        $returnExpress->address = implode(' ', array_filter([$returnAddress->province_name, $returnAddress->city_name, $returnAddress->district_name, $returnAddress->street_name, $returnAddress->address]));
                        $returnExpress->contacts_info = ['contacts_name'=> $returnAddress->contact,'mobile'=> $returnAddress->mobile,];
                    }
                }
            });
        }


        $refundApply->resend_express_id = 0;
        if ($refundApply->refund_type == RefundApply::REFUND_TYPE_EXCHANGE_GOODS && $refundApply->hasManyResendExpress->count() == 1) {
            $refundApply->resend_express_id = $refundApply->hasManyResendExpress->first()->id;
        }

        $refundApply->remark = $refundApply->remark ?: '';

        //判断是门店还是供应商
        $plugin = RefundApply::getIsPlugin($refundApply->order_id);
        if($plugin->is_plugin) {
            $refundApply->is_plugin = $plugin->is_plugin;
            $refundApply->supplier_id = RefundApply::getSupplierId($refundApply->order_id);
        }
        if ($plugin->plugin_id == 32) {
            $refundApply->plugin_id = $plugin->plugin_id;
            $refundApply->store_id = RefundApply::getStoreId($refundApply->order_id);
        }

        $refundApply->reject_time = $refundApply->reject_time ? date('Y-m-d H:i:s') : '';

        $refundApply['other_data'] = RefundService::getSendBackWayDetailData($refundApply);

        $refundService = \app\common\modules\refund\RefundOrderFactory::getInstance()->getRefundDetail($refundApply);

        $refundApply->return_address_list = $refundService->getReturnAddress();

        $msg = $refundApply->uid != \YunShop::app()->getMemberId() ? '信息不属于-'.\YunShop::app()->getMemberId() : '成功';

        return $this->successJson($msg,$refundApply);
    }


    /**
     * 获取退款类型名称和值
     *
     * @param RefundApply $refundApply
     * @return array ['name' => string, 'value' => int]
     */
    protected function getRefundTypeInfo(RefundApply $refundApply): array
    {
        $name = '';
        $value = 0;

        $order = $refundApply->order;
        $orderType = $order->order_type;
        $orderStatus = $order->status;

        if (in_array($orderType, [1, 2, 3, 4])) {
            // 订单类型1-4都是退款
            $name = '退款';
            $value = RefundApply::REFUND_TYPE_REFUND_MONEY;
        } elseif ($orderType == 5) {
            // 订单类型5根据状态区分
            if ($orderStatus == Order::WAIT_PAY) {
                $name = '取消订单';
                $value = 1; // 取消订单
            } elseif ($orderStatus == Order::COMPLETE) {
                $name = '退款';
                $value = RefundApply::REFUND_TYPE_REFUND_MONEY;
            }
        }

        return [[
            'name' => $name,
            'value' => $value
        ]];
    }



    public function getDetail($refund_id){

        $refundApply = RefundApply::detail()->withoutGlobalScope('uid')->find($refund_id);

       $refundApply->refundTypes = $this->getRefundTypeInfo($refundApply);


        //兼容用户寄回物流显示旧数据
        /*if ($refundApply->hasManyReturnExpress->isNotEmpty()) {
            $refundApply->hasManyReturnExpress->map(function ($returnExpress) use ($refundApply) {
                if (!$returnExpress->address && $refundApply->refund_address) {
                    $returnAddress = \app\common\models\goods\ReturnAddress::where('id', $refundApply->refund_address)->first();
                    if ($returnAddress) {
                        $returnExpress->address = implode(' ', array_filter([$returnAddress->province_name, $returnAddress->city_name, $returnAddress->district_name, $returnAddress->street_name, $returnAddress->address]));
                        $returnExpress->contacts_info = ['contacts_name'=> $returnAddress->contact,'mobile'=> $returnAddress->mobile,];
                    }
                }
            });
        }*/


//        $refundApply->resend_express_id = 0;
//        if ($refundApply->refund_type == RefundApply::REFUND_TYPE_EXCHANGE_GOODS && $refundApply->hasManyResendExpress->count() == 1) {
//            $refundApply->resend_express_id = $refundApply->hasManyResendExpress->first()->id;
//        }

        $refundApply->remark = $refundApply->remark ?: '';



        //判断是门店还是供应商
      /*  $plugin = RefundApply::getIsPlugin($refundApply->order_id);
        if($plugin->is_plugin) {
            $refundApply->is_plugin = $plugin->is_plugin;
            $refundApply->supplier_id = RefundApply::getSupplierId($refundApply->order_id);
        }
        if ($plugin->plugin_id == 32) {
            $refundApply->plugin_id = $plugin->plugin_id;
            $refundApply->store_id = RefundApply::getStoreId($refundApply->order_id);
        }*/

        $refundApply->reject_time = $refundApply->reject_time ? date('Y-m-d H:i:s') : '';

       // $refundApply['other_data'] = RefundService::getSendBackWayDetailData($refundApply);
        $project_data = Order::select('id',"project_id","order_sn","status","goods_total","goods_price","pay_time",'order_type','first_pay_time','last_pay_time')->with(['project'=>function($query){
            $query->select("id","name");
        },'orderPayments','myOrderAddress'=>function($query){
            $query->select("id","order_main_id","address","mobile","realname");
        }])->find($refundApply->order_id);

        $payTime = $project_data->first_pay_time;


        // 计算3天后的时间
        $threeDaysLater = $payTime->copy()->addHours(72);
        $now = Carbon::now();
        $refundApply->hour72 = $now->lessThan($threeDaysLater)?1:0;

//        $address = Address::whereIn('id',[$project_data->project->province_id,$project_data->project->city_id,$project_data->project->district_id])->pluck("areaname")->toArray();
//        $project_data->address_detail = $address[0].$address[1].$address[2].$project_data->project->address_detail;
        $refundApply->project_data = $project_data;
//        $refundService = \app\common\modules\refund\RefundOrderFactory::getInstance()->getRefundDetail($refundApply);
//
//        $refundApply->return_address_list = $refundService->getReturnAddress();

        $msg = $refundApply->uid != \YunShop::app()->getMemberId() ? '信息不属于-'.\YunShop::app()->getMemberId() : '成功';
        //$orderIds = Order::where('parent_id',$refundApply->order_id)->pluck('id')->toArray();
        //$refundIds = \app\common\models\refund\RefundApply::whereIn('order_id',$orderIds)->where('status','!=',-2)->pluck('id')->toArray();


        /*$refundGoodsLog = RefundGoodsLog::whereIn('refund_id',$refundIds)->get()->map(function ($item){

            $item->id = $item->order_goods_id;
            $item->total = $item->refund_total;
            $item->thumb = $item->goods_thumb;
            $item->material_color = $item->component_data;
            return $item;
        });*/

        $processLog = RefundProcessLog::where('refund_id', $refundApply->id)
            ->with(['member' => function ($query) {
                $query->select("uid", "nickname", "avatar");
            }])
            ->get()
            ->map(function ($log) {
                if ($log->member && isset($log->member->avatar)) {
                    // 假设 logo 存在但是相对路径，需要拼接域名
                    $log->member->avatar = yz_tomedia($log->member->avatar);
                }
                return $log;
            });
        $refundApply->processLog = $processLog;
        if($refundApply->status == 1 || $refundApply->progress_status == 1){
            $refundApply->refund_status = 3;
        }else{
            $refundApply->refund_status = 2;
        }
        $refundApply->service_link = ServiceUser::getDistributeService(0);
        $refundApply->remainingSeconds = max(0, $refundApply->timeout_time - time());
     /*   if($refundApply->order->parent_id == 0){
           $refundApply->refund_goods = $refundGoodsLog;
        }else{
           $refundApply->refund_goods = $refundApply->refundOrderGoods->map(function ($item){

               $item->id = $item->order_goods_id;
               $item->total = $item->refund_total;
               $item->thumb = $item->goods_thumb;
               $item->material_color = $item->component_data;
               return $item;
           });
        }*/

        return $this->successJson($msg,$refundApply);
    }


    public function processLog()
    {
        $list = RefundProcessLog::where('refund_id', request()->input('refund_id'))->get();

        return $this->successJson('list', $list);
    }

    public function aa()
    {

    }
}