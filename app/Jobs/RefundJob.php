<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2023/4/19
 * Time: 17:38
 */

namespace app\Jobs;

use app\backend\modules\finance\services\CommonService;
use app\backend\modules\refund\models\RefundApply;
use app\backend\modules\refund\services\RefundOperationService;
use app\common\exceptions\ShopException;
use app\common\facades\Setting;

use app\common\modules\refund\services\RefundService;
use app\frontend\models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RefundJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;


    public $tries = 1;



    public $timeout = 300;


    public $uniacid;
    public $is_all;

    public $refund_id;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($uniacid,$is_all,$refund_id)
    {
        $this->uniacid = $uniacid;
        $this->is_all = $is_all;
        $this->refund_id = $refund_id;
    }

    /**
     * @return bool|void
     */
    public function handle()
    {
        \YunShop::app()->uniacid = Setting::$uniqueAccountId = $this->uniacid;
        $RefundApply =  RefundApply::find($this->refund_id);
        $payTime = $RefundApply->order->pay_time;

        // 计算3天后的时间
        $threeDaysLater = $payTime->copy()->addHours(72);

        // 获取当前时间
        $now = Carbon::now();


        if($RefundApply->status != 0){
            return false;
        }
        if(in_array($RefundApply->status,[6,7])){
            return false;
        }

        if($RefundApply->order->pay_type_id == 16){
            //汇款支付不能自动退款
            return false;
        }

        if(!$now->lessThan($threeDaysLater)){
            //大于3天按正常流程
            return false;
        }

        $refund_id = $RefundApply->id;

          //if($this->is_all == 1){
            //整单商品退款

            try {
                /**
                 * @var $this ->refundApply RefundApply
                 */
                $result = DB::transaction(function () use ($refund_id) {
                    $result = (new RefundService)->pay($refund_id);
                    if (!$result) {
                        \Log::debug('<------售后自动退款失败------');
                    }
                    return $result;
                });
                $RefundApply->status = 1;
                $RefundApply->progress_status = 2;
                $RefundApply->supplier_pass_time = time();
                $RefundApply->save();
                $this->transPlat($RefundApply->order,$RefundApply->price);
            } catch (\Exception $e) {
                \Log::debug('<------售后退款失败------:'.$e->getMessage());
            }
     //   }
        /*elseif ($this->is_all == 2){
            //预付款定金退部分商品
             RefundOperationService::refundPartial($refund_id);
        }*/

        Order::where('id',$RefundApply->order->id)->update(['refund_status'=>2]);
    }

    private function transPlat($order,$refund_price)
    {

        $data['order'] = $order;
        $data['income'] = CommonService::PAY_COME;
        $data['supplier_id'] = 0;
        $data['income_type'] = $order->order_type;
        $data['pay_form'] = CommonService::PLAT_TO_MEMBER;
        $data['pay_price'] = $refund_price;
        $data['payment_stage'] = $order->hasOneOrderPay->payment_stage;
        $commonService = new CommonService();
        //查询订单信息，并通知厂家收款
        $commonService->submitSupplierOrderPay($data);
    }





}