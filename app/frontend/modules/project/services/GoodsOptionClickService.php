<?php

namespace app\frontend\modules\project\services;

use app\common\models\GoodsOption;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use app\common\models\goods\GoodsOptionClick;
use app\common\models\MemberHistory;
class GoodsOptionClickService
{


    /**
     * Redis 键前缀
     */
    protected const REDIS_PREFIX = 'goods_option:click:';

    /**
     * IP 记录过期时间（秒）- 可以设置为商品生命周期或固定时间
     */
    protected const IP_EXPIRE_SECONDS = 86400 * 30; // 30天

    /**
     * 点击计数缓存时间
     */
    protected const COUNT_CACHE_SECONDS = 3600; // 1小时

    /**
     * 记录点击（使用 Redis 进行 IP 去重）
     * 
     * @param string $optionId
     * @param string|null $ip
     * @param int $expireSeconds IP记录过期时间
     * @return array
     */
    public function recordClick(string $optionId, ?string $ip = null, ?int $expireSeconds = null): array
    {
        $ip = $ip ?? request()->ip();
        $expireSeconds = $expireSeconds ?? self::IP_EXPIRE_SECONDS;

        // Redis 键名
        $ipKey = $this->getIpKey($optionId, $ip);

        // 使用 Redis 的 SETNX 原子操作检查是否已点击
        // SETNX = SET if Not eXists
        $isNewClick = Redis::setnx($ipKey, 1);
        $goodsOption = GoodsOption::where('id', $optionId)->first();
        $memberId = \YunShop::app()->getMemberId();

        // 记录详细点击日志到数据库
        $clickRecord = $this->recordClickDetail(
            $optionId,
            $goodsOption->goods_id,
            $ip,
            $memberId
        );
        //记录
        if ($isNewClick) {
            // 设置过期时间
            Redis::expire($ipKey, $expireSeconds);

            // 增加数据库中的点击计数
            $incremented = $this->incrementClickCount($optionId);

            // 异步记录点击详情到数据库（可选）
            //  $this->recordClickDetailAsync($optionId, $ip);

            //增加浏览记录
            MemberHistory::create([
                'member_id'=>$memberId,
                'uniacid' => \YunShop::app()->uniacid,
                'goods_id' => $goodsOption->goods_id,
                'option_id' => $optionId,
                'supplier_id' => $goodsOption->supp_id,
        
            ]);

            return [
                'success' => true,
                'incremented' => $incremented,
                'message' => '点击记录成功，计数已增加',
                'ip_recorded' => true,
                'click_count' => $this->getClickCount($optionId)
            ];
        }

        return [
            'success' => false,
            'incremented' => false,
            'message' => '该IP在有效期内已点击过，计数未增加',
            'ip_recorded' => false,
            'click_count' => $this->getClickCount($optionId)
        ];
    }


    /**
     * 记录详细的点击日志到数据库
     */
    protected function recordClickDetail(
        string $optionId,
        int $goodsId,
        string $ip,
        int $memberId

    ) {


        // 创建点击记录
        $clickRecord = GoodsOptionClick::create([
            'member_id' => $memberId,
            'ip_address' => $ip,
            'user_agent' => request()->userAgent(),
            'goods_id' => $goodsId,
            'option_id' => $optionId,
            'clicked_at' => time(),
            'referer_url' => request()->header('referer'),
            'page_url' => request()->fullUrl(),
            'is_purchased' => 0,

        ]);


        return $clickRecord;
    }

    /**
     * 批量记录点击
     */
    public function recordBatchClicks(array $optionIds, ?string $ip = null): array
    {
        $results = [];
        $ip = $ip ?? request()->ip();

        foreach ($optionIds as $optionId) {
            $results[$optionId] = $this->recordClick($optionId, $ip);
        }

        return $results;
    }

    /**
     * 增加数据库点击计数
     */
    protected function incrementClickCount(string $optionId): bool
    {
        try {
            // 使用数据库事务
            \DB::transaction(function () use ($optionId) {
                // 先尝试更新现有记录
                $updated = GoodsOption::where('id', $optionId)
                    ->increment('click_num');



                // 清除点击计数缓存
                $this->clearClickCountCache($optionId);
            });

            return true;
        } catch (\Exception $e) {
            \Log::error("增加点击计数失败: " . $e->getMessage(), [
                'option_id' => $optionId
            ]);
            return false;
        }
    }

    /**
     * 获取点击数量（带缓存）
     */
    public function getClickCount(string $optionId): int
    {
        $cacheKey = $this->getCountCacheKey($optionId);

        return Cache::remember($cacheKey, self::COUNT_CACHE_SECONDS, function () use ($optionId) {
            $option = GoodsOption::where('id', $optionId)->first();
            return $option ? $option->click_num : 0;
        });
    }

    /**
     * 批量获取点击数量
     */
    public function getBatchClickCounts(array $optionIds): array
    {
        $results = [];

        foreach ($optionIds as $optionId) {
            $results[$optionId] = $this->getClickCount($optionId);
        }

        return $results;
    }

    /**
     * 检查IP是否已点击过某个选项
     */
    public function hasIpClicked(string $optionId, ?string $ip = null): bool
    {
        $ip = $ip ?? request()->ip();
        $ipKey = $this->getIpKey($optionId, $ip);

        return (bool) Redis::exists($ipKey);
    }

    /**
     * 获取选项的所有点击IP数量（去重后的UV）
     */
    public function getUniqueClickCount(string $optionId): int
    {
        $pattern = $this->getIpKey($optionId, '*');

        // 使用 SCAN 命令避免阻塞（生产环境推荐）
        $cursor = 0;
        $count = 0;

        do {
            [$cursor, $keys] = Redis::scan($cursor, 'match', $pattern);
            $count += count($keys);
        } while ($cursor != 0);

        return $count;
    }

    /**
     * 清理过期的IP记录（可选定时任务）
     */
    public function cleanupExpiredIpRecords(): int
    {
        // Redis 会自动清理过期的键
        // 此方法可用于额外的清理逻辑
        return 0;
    }

    /**
     * 清除指定选项的所有IP记录
     */
    public function clearIpRecords(string $optionId): bool
    {
        $pattern = $this->getIpKey($optionId, '*');

        $keys = Redis::keys($pattern);
        if (!empty($keys)) {
            Redis::del($keys);
        }

        return true;
    }

    /**
     * 异步记录点击详情（使用队列）
     */
    protected function recordClickDetailAsync(string $optionId, string $ip): void
    {
        // 如果需要记录详细的点击日志，可以使用队列异步处理
        // dispatch(new RecordOptionClickJob($optionId, $ip, [
        //     'user_agent' => request()->userAgent(),
        //     'referer' => request()->header('referer'),
        //     'timestamp' => now()->toDateTimeString()
        // ]));
    }

    /**
     * 清除点击计数缓存
     */
    protected function clearClickCountCache(string $optionId): void
    {
        Cache::forget($this->getCountCacheKey($optionId));
    }

    /**
     * 生成Redis IP记录键
     */
    protected function getIpKey(string $optionId, string $ip): string
    {
        $ipHash = md5($ip); // 对IP进行哈希，保护隐私
        return self::REDIS_PREFIX . "{$optionId}:ip:{$ipHash}";
    }

    /**
     * 生成点击计数缓存键
     */
    protected function getCountCacheKey(string $optionId): string
    {
        return "goods_option_count:{$optionId}";
    }
}
