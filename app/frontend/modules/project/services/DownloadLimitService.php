<?php

namespace app\frontend\modules\project\services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

/**
 * 下载限流服务 - 恶意 IP / 用户下载行为检测
 *
 * 三层防护策略：
 *   Layer 1 - IP + 商品ID + 文件类型 分钟级频率：每分钟最多 N 次
 *   Layer 2 - IP + 商品ID + 文件类型 小时级配额：每小时最多 N 次
 *   Layer 3 - IP 全局黑名单：触发阈值后自动拉黑，封禁 BLACKLIST_TTL 秒
 *
 * 限流维度：同一 IP 对同一商品同一类型文件的下载次数独立计数，
 * 不同商品之间互不影响；黑名单是 IP 维度全局生效。
 * 所有计数与黑名单均存于 Redis，自动过期，无需人工清理。
 */
class DownloadLimitService
{
    // ─── 配置常量 ────────────────────────────────────────────────────────────

    /**
     * 各文件类型每分钟允许的最大下载次数
     * key = file_type 值，value = 次数上限
     */
    const LIMIT_PER_MINUTE = [
        '3d'         => 10,  // 3D 模型：每分钟 10 次
        'cad'        => 15,  // CAD 文件：每分钟 15 次
        'atlas'      => 20,  // 图册 PDF：每分钟 20 次
        'color_card' => 20,  // 色卡：每分钟 20 次
    ];

    /**
     * 各文件类型每小时允许的最大下载次数
     */
    const LIMIT_PER_HOUR = [
        '3d'         => 100,
        'cad'        => 150,
        'atlas'      => 300,
        'color_card' => 300,
    ];

    /** 触发封禁后，黑名单有效期（秒）默认 10 分钟（宽松模式）*/
    const BLACKLIST_TTL = 600;

    /** 连续触发分钟级超限 N 次后自动拉黑整个 IP */
    const BLACKLIST_TRIGGER_CONSECUTIVE = 10;

    // ─── Redis Key 前缀 ──────────────────────────────────────────────────────

    const KEY_MINUTE    = 'dl_limit_min:';   // IP+商品+类型 分钟级计数
    const KEY_HOUR      = 'dl_limit_hour:';  // IP+商品+类型 小时级计数
    const KEY_BLACKLIST = 'dl_blacklist:';   // IP 全局黑名单
    const KEY_EXCEED    = 'dl_exceed_cnt:';  // IP + 类型 连续超限计数（各类型互不叠加）

    // ─── 公开方法 ────────────────────────────────────────────────────────────

    /**
     * 检查当前 IP 是否允许下载指定商品的指定类型文件
     *
     * @param  string $ip       客户端 IP
     * @param  int    $goodsId  商品 ID
     * @param  string $fileType 文件类型：3d | cad | atlas | color_card
     * @param  int    $uid      当前用户 ID（0 = 未登录）
     * @return array  ['allow' => bool, 'reason' => string, 'retry_after' => int]
     */
    public function check(string $ip, int $goodsId, string $fileType, int $uid = 0): array
    {
        // 1. 全局黑名单优先判断
        if ($this->isBlacklisted($ip)) {
            $ttl = Redis::ttl(self::KEY_BLACKLIST . $ip);
            return $this->deny('您的 IP 已被限制下载，请稍后再试', $ttl > 0 ? $ttl : self::BLACKLIST_TTL);
        }

        $maxMinute = self::LIMIT_PER_MINUTE[$fileType] ?? 5;
        $maxHour   = self::LIMIT_PER_HOUR[$fileType]   ?? 20;

        // 2. 分钟级限流（按 IP + 商品ID + 文件类型 独立计数）
        $minuteCount = $this->incrementMinute($ip, $goodsId, $fileType);
        if ($minuteCount > $maxMinute) {
            // 超限计数也按 IP + fileType 隔离，避免不同类型叠加误封
            $this->recordExceed($ip, $fileType);
            $this->tryAutoBlacklist($ip, $fileType);
            return $this->deny('下载过于频繁，请稍后再试', 60);
        }

        // 3. 小时级配额（按 IP + 商品ID + 文件类型 独立计数）
        $hourCount = $this->incrementHour($ip, $goodsId, $fileType);
        if ($hourCount > $maxHour) {
            $this->autoBlacklist($ip, "goods:{$goodsId} {$fileType} 小时级配额超限");
            return $this->deny('该文件今日下载次数已达上限，请明天再试', 3600);
        }

        // 4. 记录本次下载日志
        $this->logDownload($ip, $goodsId, $fileType, $uid);

        return ['allow' => true, 'reason' => '', 'retry_after' => 0];
    }

    /**
     * 手动将某 IP 加入黑名单（可由后台管理员调用）
     *
     * @param  string $ip
     * @param  int    $ttl  封禁时长（秒），0 表示永久（1 年）
     * @param  string $reason
     */
    public function blacklist(string $ip, int $ttl = 0, string $reason = '手动封禁'): void
    {
        $expiry = $ttl > 0 ? $ttl : 365 * 86400;
        Redis::setex(self::KEY_BLACKLIST . $ip, $expiry, $reason);
        Log::warning("[DownloadLimit] IP 已被手动加入黑名单", [
            'ip'     => $ip,
            'ttl'    => $expiry,
            'reason' => $reason,
        ]);
    }

    /**
     * 手动解除某 IP 的黑名单
     */
    public function unblacklist(string $ip): void
    {
        Redis::del(self::KEY_BLACKLIST . $ip);
        Redis::del(self::KEY_EXCEED . $ip);
        Log::info("[DownloadLimit] IP 黑名单已解除", ['ip' => $ip]);
    }

    /**
     * 获取某 IP 对某商品的当前限流状态（用于后台监控）
     *
     * @param  string $ip
     * @param  int    $goodsId
     * @return array
     */
    public function getStatus(string $ip, int $goodsId = 0): array
    {
        $typeCounts = [];
        foreach (array_keys(self::LIMIT_PER_MINUTE) as $type) {
            $typeCounts[$type] = [
                'minute_count'  => (int) Redis::get(self::KEY_MINUTE . $ip . ':' . $goodsId . ':' . $type . ':' . $this->minuteSlot()),
                'hour_count'    => (int) Redis::get(self::KEY_HOUR   . $ip . ':' . $goodsId . ':' . $type . ':' . $this->hourSlot()),
                'exceed_count'  => (int) Redis::get(self::KEY_EXCEED . $ip . ':' . $type),
                'minute_limit'  => self::LIMIT_PER_MINUTE[$type],
                'hour_limit'    => self::LIMIT_PER_HOUR[$type],
                'trigger_limit' => self::BLACKLIST_TRIGGER_CONSECUTIVE,
            ];
        }
        return [
            'ip'               => $ip,
            'goods_id'         => $goodsId,
            'is_blacklisted'   => $this->isBlacklisted($ip),
            'blacklist_ttl'    => (int) Redis::ttl(self::KEY_BLACKLIST . $ip),
            'blacklist_reason' => Redis::get(self::KEY_BLACKLIST . $ip) ?? '',
            'exceed_count'     => (int) Redis::get(self::KEY_EXCEED . $ip),
            'type_counts'      => $typeCounts,
        ];
    }

    // ─── 私有方法 ────────────────────────────────────────────────────────────

    /**
     * 判断 IP 是否在黑名单中
     */
    private function isBlacklisted(string $ip): bool
    {
        return (bool) Redis::exists(self::KEY_BLACKLIST . $ip);
    }

    /**
     * 分钟级计数 +1（按 IP + 商品ID + 文件类型 隔离）
     * Key 格式：dl_limit_min:{ip}:{goodsId}:{fileType}:{YmdHi}
     */
    private function incrementMinute(string $ip, int $goodsId, string $fileType): int
    {
        $key   = self::KEY_MINUTE . $ip . ':' . $goodsId . ':' . $fileType . ':' . $this->minuteSlot();
        $count = Redis::incr($key);
        if ($count === 1) {
            Redis::expire($key, 120); // 保留 2 分钟，覆盖窗口边界
        }
        return (int) $count;
    }

    /**
     * 小时级计数 +1（按 IP + 商品ID + 文件类型 隔离）
     * Key 格式：dl_limit_hour:{ip}:{goodsId}:{fileType}:{YmdH}
     */
    private function incrementHour(string $ip, int $goodsId, string $fileType): int
    {
        $key   = self::KEY_HOUR . $ip . ':' . $goodsId . ':' . $fileType . ':' . $this->hourSlot();
        $count = Redis::incr($key);
        if ($count === 1) {
            Redis::expire($key, 7200); // 保留 2 小时
        }
        return (int) $count;
    }

    /**
     * 记录连续超限次数（按 IP + fileType 隔离，各类型独立计数，不互相叠加）
     * Key 格式：dl_exceed_cnt:{ip}:{fileType}
     */
    private function recordExceed(string $ip, string $fileType): void
    {
        $key   = self::KEY_EXCEED . $ip . ':' . $fileType;
        $count = Redis::incr($key);
        if ($count === 1) {
            Redis::expire($key, 3600);
        }
    }

    /**
     * 判断某类型是否达到自动拉黑阈值
     * 只有同一类型文件连续超限 BLACKLIST_TRIGGER_CONSECUTIVE 次才封禁整个 IP，
     * 避免 cad + 3d + atlas 各超限几次叠加误封正常用户。
     */
    private function tryAutoBlacklist(string $ip, string $fileType): void
    {
        $key    = self::KEY_EXCEED . $ip . ':' . $fileType;
        $exceed = (int) Redis::get($key);
        if ($exceed >= self::BLACKLIST_TRIGGER_CONSECUTIVE) {
            $this->autoBlacklist($ip, "{$fileType} 分钟级频率连续超限 {$exceed} 次");
        }
    }

    /**
     * 自动触发黑名单
     */
    private function autoBlacklist(string $ip, string $reason): void
    {
        if (!$this->isBlacklisted($ip)) {
            Redis::setex(self::KEY_BLACKLIST . $ip, self::BLACKLIST_TTL, $reason);
            Log::warning("[DownloadLimit] IP 已被自动加入黑名单", [
                'ip'     => $ip,
                'reason' => $reason,
                'ttl'    => self::BLACKLIST_TTL,
            ]);
        }
    }

    /**
     * 记录下载日志到 Redis（日志 List，最多保留 1000 条）
     */
    private function logDownload(string $ip, int $goodsId, string $fileType, int $uid): void
    {
        $logKey = 'dl_log:' . date('Ymd');
        $entry  = json_encode([
            'ip'        => $ip,
            'uid'       => $uid,
            'goods_id'  => $goodsId,
            'file_type' => $fileType,
            'time'      => date('Y-m-d H:i:s'),
        ]);
        Redis::lpush($logKey, $entry);
        Redis::ltrim($logKey, 0, 999);    // 每日最多保留 1000 条
        Redis::expire($logKey, 7 * 86400); // 保留 7 天
    }

    /**
     * 构造拒绝响应
     */
    private function deny(string $reason, int $retryAfter = 60): array
    {
        return [
            'allow'       => false,
            'reason'      => $reason,
            'retry_after' => $retryAfter,
        ];
    }

    /**
     * 当前分钟时间槽标识，格式 YmdHi
     */
    private function minuteSlot(): string
    {
        return date('YmdHi');
    }

    /**
     * 当前小时时间槽标识，格式 YmdH
     */
    private function hourSlot(): string
    {
        return date('YmdH');
    }
}
