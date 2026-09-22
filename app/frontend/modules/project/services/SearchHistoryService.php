<?php


namespace app\frontend\modules\project\services;


use Illuminate\Support\Facades\Redis;

class SearchHistoryService
{
    const MAX_RECORDS = 10; // 每个用户最多保存10条记录
    const KEY_PREFIX = 'user_search_goods_history';

    /**
     * 添加搜索记录
     *
     * @param string $keyword
     * @param int $userId
     * @return bool
     */
    public static function addSearchHistory($key_type,$keyword, $userId)
    {
        if (!$userId) {
            return false;
        }
        $key_prefix = self::getKeyPrefix($key_type);
        $key = $key_prefix . $userId;
        $timestamp = now()->timestamp;

        // 先删除已存在的相同关键词
        Redis::zrem($key, $keyword);

        // 再添加新的记录（确保关键词唯一）
        Redis::zadd($key, $timestamp, $keyword);

        // 保持最多MAX_RECORDS条记录，移除最旧的
        $count = Redis::zcard($key);
        if ($count > self::MAX_RECORDS) {
            Redis::zremrangebyrank($key, 0, $count - self::MAX_RECORDS - 1);
        }

        return true;
    }

    /**
     * 获取用户搜索历史
     *
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public static function getSearchHistory($key_type,$userId = null, $limit = 10)
    {
        $key_prefix = self::getKeyPrefix($key_type);
        if (!$userId) {
            return [];
        }

        $key = $key_prefix . $userId;

        // 获取最新的搜索记录，按时间倒序
        $history = Redis::zrevrange($key, 0, $limit - 1);

        return $history ?: [];
    }

    /**
     * 清除用户搜索历史
     *
     * @param int $userId
     * @return bool
     */
    public static function clearSearchHistory($key_type,$userId = null)
    {

        $key_prefix = self::getKeyPrefix($key_type);
        if (!$userId) {
            return false;
        }

        $key = $key_prefix . $userId;

        return Redis::del($key) > 0;
    }



    /**
     * 清除特定的搜索关键词
     *
     * @param int $key_type 搜索类型
     * @param string $keyword 要删除的关键词
     * @param int $userId 用户ID
     * @return bool
     */
    public static function removeSingleKeyword($key_type, $keyword, $userId = null)
    {
        if (!$userId || !$keyword) {
            return false;
        }

        $key_prefix = self::getKeyPrefix($key_type);
        $key = $key_prefix . $userId;

        // 从有序集合中删除特定的关键词
        $result = Redis::zrem($key, $keyword);

        return $result > 0;
    }

    protected static function getKeyPrefix($key_type)
    {
        $array_key = [
            1=>"user_search_goods_history",
            2=>"user_search_brand_history",
            3=>"user_search_case_history"
        ];
        return $array_key[$key_type];
    }
}