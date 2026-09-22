<?php

namespace app\backend\modules\goods\services;

use Illuminate\Support\Facades\Redis;
use app\common\models\GoodsOption;

class ModelSyncService
{

    const REDIS_GOODS_PREFIX = 'goods_upload_model:';
    const REDIS_STATUS_PREFIX = 'status_upload_model:';
    
    /**
     * 获取商品下所有规格的同步状态
     */
    public static function getGoodsSyncStatus($goods_id)
    {
        $redis = Redis::connection();
        $goodsKey = self::REDIS_GOODS_PREFIX . $goods_id;
        
        // 获取该商品下所有进行过同步的 option_id
        $optionIds = $redis->smembers($goodsKey) ?: [];
        
        if (empty($optionIds)) {
             // 如果 Redis 中没有记录，从数据库获取该商品的所有 option
            $goodsOptions = GoodsOption::where('goods_id', $goods_id)
                ->select('id', 'd3ModelUrl_ori', 'd3ModelUrl_weld')
                ->get();
            
            // 检查是否所有选项的3D模型URL都不为空
            $allOptionsHave3DModels = true;
            $optionIds = [];
            
            foreach ($goodsOptions as $option) {
                $optionIds[] = $option->id;
                
                // 检查3D模型URL是否为空
                if (empty($option->d3ModelUrl_ori) || empty($option->d3ModelUrl_weld)) {
                    $allOptionsHave3DModels = false;
                }
            }
            
            // 如果所有选项的3D模型URL都不为空，直接返回100%状态
            if (!empty($optionIds) && $allOptionsHave3DModels) {
                return [
                    'goods_id' => $goods_id,
                    'total_options' => count($optionIds),
                    'all_3d_models_completed' => true,
                    'options' => array_fill_keys($optionIds, [
                        '3d_model' => [
                            'status' => 'completed',
                            'progress' => 100,
                            'message' => '3D模型已全部生成',
                            'timestamp' => time()
                        ]
                    ]),
                    'summary' => [
                        'completed' => count($optionIds),
                        'processing' => 0,
                        'failed' => 0,
                        'pending' => 0
                    ],
                    'overall_progress' => 100
                ];
            }
        
        // 将 option_id 记录到 Redis
        // if (!empty($optionIds)) {
        //     $redis->sadd($goodsKey, ...$optionIds);
        //     $redis->expire($goodsKey, 86400);
        // }
     }
        
        $result = [
            'goods_id' => $goods_id,
            'total_options' => count($optionIds),
            'options' => [],
            'summary' => [
                'completed' => 0,
                'processing' => 0,
                'failed' => 0,
                'pending' => 0
            ]
        ];
        
        foreach ($optionIds as $optionId) {
            // 获取所有类型的同步状态
            $statuses = self::getAllTypeOptionSyncStatus($optionId);
            
            foreach ($statuses as $type => $status) {
                $result['options'][$optionId][$type] = $status;
                $result['summary'][$status['status']]++;
            }
        }
        
        return $result;
    }
    
    /**
     * 获取单个规格所有类型的同步状态
     */
    public static function getAllTypeOptionSyncStatus($option_id)
    {
        $types = [1, 2]; // 根据UploadModelJob.php中的定义，只有这4种类型
        $statuses = [];
        
        foreach ($types as $type) {
            $statuses[$type] = self::getOptionSyncStatus($option_id, $type);
        }
        
        return $statuses;
    }
    
    /**
     * 获取单个规格指定类型的同步状态
     */
    public static function getOptionSyncStatus($option_id, $type = null)
    {
        $redis = Redis::connection();
        
        // 如果没有指定类型，则尝试获取所有类型的状??
        if ($type === null) {
            return self::getAllTypeOptionSyncStatus($option_id);
        }
        
        $statusKey = self::REDIS_STATUS_PREFIX . $option_id . ':' . $type;
        
        // 从 Redis 获取状态
        $statusData = $redis->hgetall($statusKey);
        
        if (empty($statusData)) {
            // 检查数据库中的实际状态
            $option = GoodsOption::where('id', $option_id)
                ->select(['id', 'goods_id', 'd3ModelUrl_ori','d3ModelUrl_weld'])
                ->first();
            
            if (!$option) {
                return [
                    'option_id' => $option_id,
                    'type' => $type,
                    'status' => 'not_found',
                    'progress' => '0%',
                    'message' => '规格不存在',
                    'has_model' => false,
                    'update_time' => null
                ];
            }
            
            // 根据类型确定对应的字段
            $modelField = self::getModelFieldByType($type);
            $hasModel = !empty($option->$modelField);
            
            return [
                'option_id' => $option_id,
                'type' => $type,
                'goods_id' => $option->goods_id,
                'status' => $hasModel ? 'completed' : 'pending',
                'progress' => $hasModel ? '100%' : '0%',
                'message' => $hasModel ? '模型已存在' : '等待同步',
                'has_model' => $hasModel,
                'show_progress' => 0,
                'model_url' => yz_tomedia($option->$modelField),
                'update_time' => null
            ];
        }
        
        // 补充数据库中的模型URL
        $modelUrl = $statusData['model_url'] ?? null;
        if (!$modelUrl && isset($statusData['option_id'])) {
            $option = GoodsOption::where('id', $statusData['option_id'])
                ->select('d3ModelUrl_ori')
                ->first();
            $modelUrl = $option->d3ModelUrl_ori ?? null;
        }
        
        return [
            'option_id' => $option_id,
            'type' => $type,
            'goods_id' => $statusData['goods_id'] ?? null,
            'status' => $statusData['status'] ?? 'unknown',
            'progress' => $statusData['progress'] ?? '0%',
            'message' => $statusData['message'] ?? ($statusData['error'] ?? ''),
            'has_model' => !empty($modelUrl),
            'show_progress'=>1,
            'model_url' => yz_tomedia($modelUrl),
            'start_time' => isset($statusData['start_time']) ? date('Y-m-d H:i:s', $statusData['start_time']) : null,
            'update_time' => isset($statusData['update_time']) ? date('Y-m-d H:i:s', $statusData['update_time']) : null,
            'error' => $statusData['error'] ?? null
        ];
    }
    
    /**
     * 根据类型获取对应的模型字段名
     */
    private static function getModelFieldByType($type)
    {
        switch ($type) {
            case 1:
                return 'd3ModelUrl_weld';
            case 2:
                return 'd3ModelUrl_ori';
            case 3:
                return 'thumb3dModelUrl_file';
            case 4:
                return 'thumb3dModelUrl';
            default:
                return 'd3ModelUrl_ori'; // 默认返回ori字段
        }
    }
    
    /**
     * 获取已同步完成的 option_id 列表
     */
    public static function getCompletedOptions($goods_id)
    {
        $status = self::getGoodsSyncStatus($goods_id);
        
        $completedOptions = [];
        foreach ($status['options'] as $optionId => $optionStatuses) {
            foreach ($optionStatuses as $type => $optionStatus) {
                if ($optionStatus['status'] === 'completed' && $optionStatus['has_model']) {
                    $completedOptions[] = [
                        'option_id' => $optionId,
                        'type' => $type,
                        'model_url' => yz_tomedia($optionStatus['model_url']),
                        'update_time' => $optionStatus['update_time']
                    ];
                }
            }
        }
        
        return $completedOptions;
    }
    
    /**
     * 获取正在处理的 option_id 列表
     */
    public static function getProcessingOptions($goods_id)
    {
        $status = self::getGoodsSyncStatus($goods_id);
        
        $processingOptions = [];
        foreach ($status['options'] as $optionId => $optionStatuses) {
            foreach ($optionStatuses as $type => $optionStatus) {
                if ($optionStatus['status'] === 'processing') {
                    $processingOptions[] = [
                        'option_id' => $optionId,
                        'type' => $type,
                        'progress' => $optionStatus['progress'],
                        'start_time' => $optionStatus['start_time'],
                        'message' => $optionStatus['message']
                    ];
                }
            }
        }
        
        return $processingOptions;
    }
    
    /**
     * 获取失败的 option_id 列表
     */
    public static function getFailedOptions($goods_id)
    {
        $status = self::getGoodsSyncStatus($goods_id);
        
        $failedOptions = [];
        foreach ($status['options'] as $optionId => $optionStatuses) {
            foreach ($optionStatuses as $type => $optionStatus) {
                if ($optionStatus['status'] === 'failed') {
                    $failedOptions[] = [
                        'option_id' => $optionId,
                        'type' => $type,
                        'error' => $optionStatus['message'] ?? $optionStatus['error'],
                        'update_time' => $optionStatus['update_time']
                    ];
                }
            }
        }
        
        return $failedOptions;
    }
    
    /**
     * 强制更新某个 option 的状态
     */
    public static function updateOptionStatus($option_id, $type, $status, $progress = null, $error = null)
    {
        $redis = Redis::connection();
        $statusKey = self::REDIS_STATUS_PREFIX . $option_id . ':' . $type;
        
        $data = [
            'status' => $status,
            'update_time' => time()
        ];
        
        if ($progress !== null) {
            $data['progress'] = $progress;
        }
        
        if ($error !== null) {
            $data['error'] = $error;
        }
        
        $redis->hmset($statusKey, $data);
        
        // 设置过期时间
        $expireTime = $status === 'completed' ? 3600 : 604800;
        $redis->expire($statusKey, $expireTime);
        
        return true;
    }
    
    /**
     * 清理过期的同步记录
     */
    public static function cleanupExpiredRecords($days = 7)
    {
        $redis = Redis::connection();
        
        // 查找所有状态键
        $keys = $redis->keys(self::REDIS_STATUS_PREFIX . '*');
        
        $deletedCount = 0;
        foreach ($keys as $key) {
            $lastUpdate = $redis->hget($key, 'update_time');
            
            // 如果超过指定天数没有更新，删除该记录
            if ($lastUpdate && (time() - $lastUpdate) > ($days * 24 * 3600)) {
                $redis->del($key);
                $deletedCount++;
            }
        }
        
        return $deletedCount;
    }
}