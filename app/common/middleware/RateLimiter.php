<?php

namespace app\common\middleware;

use Illuminate\Support\Facades\Redis;
use app\common\traits\JsonTrait;
class RateLimiter
{
    use JsonTrait;
    
    public function handle($request, \Closure $next, $maxRequests = 60, $timeWindow = 60)
    {
        $ip = $request->ip();
        $userId = \YunShop::app()->uid ?? 0;
        $key = "rate_limit:" . ($userId ?: $ip);
        $now = microtime(true);
        $windowStart = $now - $timeWindow;

        // 使用有序集合存储请求时间戳
        Redis::zadd($key, $now, $now);
        Redis::zremrangebyscore($key, 0, $windowStart);

        $currentRequests = Redis::zcard($key);

        if ($currentRequests > $maxRequests) {
         
            return $this->errorJsonV2('请求过于频繁，请稍后再试', [], 429);
        }

        Redis::expire($key, $timeWindow);
        return $next($request);
    }
}
