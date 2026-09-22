<?php
/**
 * Created by PhpStorm.
 * User: blank
 * Date: 2020/3/27
 * Time: 15:26
 */

namespace app\common\cron;

use app\backend\modules\survey\models\CronHeartbeat;
use app\backend\modules\survey\models\JobHeartbeatJob;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Bus\DispatchesJobs;

class HeartbeatStatusLog
{
    use DispatchesJobs;

    public function handle()
    {
        CronHeartbeat::insert(['execution_time' => time()]);
        $job = new JobHeartbeatJob();
        dispatch($job);
    }
}