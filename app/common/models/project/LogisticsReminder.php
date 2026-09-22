<?php

namespace app\common\models\project;

use app\common\models\BaseModel;
use Illuminate\Support\Carbon;
use app\common\modules\pcnotice\Template;
class LogisticsReminder extends BaseModel
{
    protected $table = 'yz_logistics_reminders';

    protected $fillable = [
        'logistics_id',
        'order_id',
        'user_id',
        'reminder_type',
        'current_status',
        'is_read',
        'reminder_data',
    ];

    protected $casts = [
        'reminder_data' => 'array',
    ];



    public static function createLogisticsReminder($logisticsTrack, $newStatus,$reminder_type)
    {
        try {
            // 获取订单信息（根据您的订单模型调整）
            $order = \app\common\models\Order::find($logisticsTrack->order_id);
            if (!$order) {
                return;
            }

            // 创建提醒记录
            self::createReminder($logisticsTrack, $order, $newStatus,$reminder_type);

           


        } catch (\Exception $e) {
            // 记录错误日志，但不影响主流程
            \Log::error('创建物流提醒失败: ' . $e->getMessage());
        }
    }

    /**
     * 创建物流提醒
     */
    public static function createReminder($logisticsTrack, $order, $newStatus,$reminder_type)
    {
        $statusText = self::getStatusText($newStatus,$reminder_type);
        
        $reminderData = [
            'status_text' => $statusText,
            'company_name' => $logisticsTrack->company_name ?? '',
            'logistics_time' => Carbon::now()->toDateTimeString(),
            'order_sn' => $order->order_sn ?? '', // 假设订单有订单号字段
        ];


         // 可以在这里添加推送通知
            $code = $reminder_type == 1?"LOGISTIC_MSG":"INSTALL_MSG";
            $option['related_id'] = $order->id;
            app('notification')->send(
                $order->uid, // 用户ID
                Template::ORDER,
                Template::ORDER_TYPE[$order->order_type],
                getNoticeTitle($order->order_type, $code),
                ['order_no' => $order->order_sn,'status_text'=>$reminderData['status_text'],'logistics_company'=>$reminderData['company_name'],'logistics_time'=>$reminderData['logistics_time']],
                $option);
        
        return self::create([
            'logistics_id' => $logisticsTrack->id,
            'order_id' => $logisticsTrack->order_id,
            'user_id' => $order->uid, // 假设订单有uid字段表示用户ID
            'reminder_type' => $reminder_type, // 状态变更
            'current_status' => $newStatus,
            'is_read' => 0,
            'reminder_data' => $reminderData
        ]);
    }

    /**
     * 获取状态文本
     */
    private static function getStatusText($status,$reminder_type)
    {
        if($reminder_type == 1){
            $statusMap = [
                1 => '接单完成',
                2 => '已发货', 
                3 => '运输中',
                4 => '已签收'
            ];
        }else{
            $statusMap = [
                1 => '接单完成', 
                2 => '安装搬运中',
                3 => '已验收'
            ];
        }
 
        return $statusMap[$status] ?? '状态更新';
    }

    /**
     * 关联物流信息
     */
    public function logistics()
    {
        return $this->belongsTo(LogisticsTracks::class, 'logistics_id');
    }

    /**
     * 关联订单
     */
    public function order()
    {
        // 根据您的订单模型调整
        return $this->belongsTo(\app\common\models\Order::class, 'order_id');
    }
}