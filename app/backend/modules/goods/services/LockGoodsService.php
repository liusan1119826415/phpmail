<?php


namespace app\backend\modules\goods\services;


use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class LockGoodsService
{
    // Redis键前缀
    const LOCK_KEY_PREFIX = 'goods_edit_lock:';
    const JOB_COUNTER_KEY_PREFIX = 'goods_edit_jobs:';
    
    /**
     * 尝试获取商品编辑锁
     * @param int $goodsId
     * @param int $timeout 锁超时时间（秒），默认300秒
     * @return bool
     */
    public static function acquireGoodsEditLock($goodsId, $timeout = 300)
    {
        $lockKey = self::LOCK_KEY_PREFIX . $goodsId;
        $lockValue = Str::random(40);
        
        // 使用Redis的setnx实现分布式锁
        $locked = Redis::set($lockKey, $lockValue, 'EX', $timeout, 'NX');
        
        if ($locked) {
            // 存储锁的值，用于后续验证
            Redis::set(self::LOCK_KEY_PREFIX . $goodsId . ':value', $lockValue, 'EX', $timeout);
            
            // 初始化任务计数器
            $counterKey = self::JOB_COUNTER_KEY_PREFIX . $goodsId;
            Redis::set($counterKey, 0);
            Redis::expire($counterKey, $timeout);
            
            \Log::info('获取商品编辑锁成功', ['goods_id' => $goodsId, 'lock_value' => $lockValue]);
            return true;
        }
        
        \Log::warning('获取商品编辑锁失败，商品正在被其他用户编辑', ['goods_id' => $goodsId]);
        return false;
    }
    
    /**
     * 释放商品编辑锁
     * @param int $goodsId
     * @return bool
     */
    public static function releaseGoodsEditLock($goodsId)
    {
        $lockKey = self::LOCK_KEY_PREFIX . $goodsId;
        $lockValueKey = self::LOCK_KEY_PREFIX . $goodsId . ':value';
        
        // 使用Lua脚本确保原子性操作，只释放自己持有的锁
        $luaScript = <<<'LUA'
            local lock_key = KEYS[1]
            local lock_value_key = KEYS[2]
            local expected_value = ARGV[1]
            
            local current_value = redis.call('get', lock_key)
            if current_value == expected_value then
                redis.call('del', lock_key)
                redis.call('del', lock_value_key)
                return 1
            end
            return 0
        LUA;
        
        $lockValue = Redis::get($lockValueKey);
        if ($lockValue) {
            $result = Redis::eval($luaScript, 2, $lockKey, $lockValueKey, $lockValue);
            if ($result) {
                \Log::info('释放商品编辑锁成功', ['goods_id' => $goodsId]);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 增加任务计数器
     * @param int $goodsId
     */
    public static function incrementJobCounter($goodsId)
    {
        $counterKey = self::JOB_COUNTER_KEY_PREFIX . $goodsId;
        Redis::incr($counterKey);
        \Log::debug('增加任务计数器', ['goods_id' => $goodsId, 'counter' => Redis::get($counterKey)]);
    }
    
    /**
     * 减少任务计数器，当计数器为0时释放锁
     * @param int $goodsId
     */
    public static function decrementJobCounter($goodsId)
    {
        $counterKey = self::JOB_COUNTER_KEY_PREFIX . $goodsId;
        $count = Redis::decr($counterKey);
        
        \Log::debug('减少任务计数器', ['goods_id' => $goodsId, 'remaining' => $count]);
        
        // 当计数器为0时，释放锁
        if ($count <= 0) {
            self::releaseGoodsEditLock($goodsId);
            // 清理计数器
            Redis::del($counterKey);
            \Log::info('所有任务完成，已释放商品编辑锁', ['goods_id' => $goodsId]);
        }
    }
    
    /**
     * 检查商品是否被锁定
     * @param int $goodsId
     * @return bool
     */
    public static function isGoodsLocked($goodsId)
    {
        $lockKey = self::LOCK_KEY_PREFIX . $goodsId;
        return Redis::exists($lockKey);
    }
}
