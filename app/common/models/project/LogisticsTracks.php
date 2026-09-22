<?php

namespace app\common\models\project;
use app\common\models\BaseModel;
use app\common\models\MemberCart;

use Illuminate\Support\Carbon;
class LogisticsTracks extends BaseModel
{
    protected $table = 'yz_logistics_tracks';

    protected $fillable = [
        'order_id',
        'current_status',
        'current_status_time',
        'track_logs',
        'company_name',
        'contact_number',
        'address',
        'package_count',
    ];

    protected $casts = [
        'track_logs' => 'array',
    ];



    public static function updateLogisticsStatus($orderId, $data)
    {

        $res = self::where('order_id',$orderId)->where('current_status',$data['current_status'])->first();
        if($res){
            return true;
        }
        $now = Carbon::now()->toDateTimeString();
        $newStatus = $data['current_status'];
        $description = $data['description'];

        // 准备初始化 track_logs
        $initialLogs = [
            [
                'status' => $newStatus,
                'description' => $description,
                'time' => $now,
            ]
        ];

        // 初始化用于 firstOrCreate 的附加字段（如果存在）
        $extraFields = [];
        if (isset($data['company_name'])) {
            $extraFields['company_name'] = $data['company_name'];
        }
        if (isset($data['contact_number'])) {
            $extraFields['contact_number'] = $data['contact_number'];
        }
        if (isset($data['address'])) {
            $extraFields['address'] = $data['address'];
        }
        if (isset($data['package_count'])) {
            $extraFields['package_count'] = $data['package_count'];
        }

        // 合并 firstOrCreate 的默认值
        $defaultValues = array_merge([
            'current_status' => $newStatus,
            'current_status_time' => $now,
            'order_id'=>$orderId,
            'track_logs' => json_encode($initialLogs),
        ], $extraFields);

        // 查找或创建记录
        $track = self::firstOrCreate(['order_id' => $orderId], $defaultValues);

        // 如果不是新建的记录，则追加状态并更新其他字段
        if (!$track->wasRecentlyCreated) {
            $logs = $track->track_logs ?? [];
            if (!is_array($logs)) {
                $logs = json_decode($logs, true) ?? [];
            }

            $logs[] = [
                'status' => $newStatus,
                'description' => $description,
                'time' => $now,
            ];

            $track->current_status = $newStatus;
            $track->current_status_time = $now;
            $track->track_logs = $logs;

            // 如果存在这些字段，则更新它们
            if (isset($data['company_name'])) {
                $track->company_name = $data['company_name'];
            }
            if (isset($data['contact_number'])) {
                $track->contact_number = $data['contact_number'];
            }
            if (isset($data['address'])) {
                $track->address = $data['address'];
            }
            if (isset($data['package_count'])) {
                $track->package_count = $data['package_count'];
            }

            $track->save();
        }

        return $track;
    }





    public static function getTracks($order_id)
    {
        $data = self::where('order_id',$order_id)->first();

        return $data;
    }


}