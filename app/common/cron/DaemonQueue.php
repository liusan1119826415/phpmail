<?php

namespace app\common\cron;

use Illuminate\Foundation\Bus\DispatchesJobs;

/**
 * Created by PhpStorm.
 * Author:  
 * Date: 2018/4/10 0010
 * Time: 下午 4:12
 */
class DaemonQueue
{
    use DispatchesJobs;

    public function handle()
    {
        $supervisor = app('supervisor');
        $supervisor->setTimeout(5000);  // microseconds
        $states = $supervisor->getState();
        foreach ($states as $state) {
            if (is_object($state) && $state->value()['statecode'] != 1) {
                $supervisor->startAllProcesses();
                break;
            }
        }
        $allProcessInfos = $supervisor->getAllProcessInfo();
        if (is_object($allProcessInfos)) {
            foreach ($allProcessInfos as $allProcessInfo) {
                foreach ($allProcessInfo->value() as $value) {
                    if ($value != 20) {
                        $supervisor->startProcess($value['group'] . ':' . $value['name']);
                    }
                }
            }
        }

        // mysql重启后，自动重启supervisor
        $serverInfo = app('db.connection')->getPdo()->getAttribute(\PDO::ATTR_SERVER_INFO);
        $tmp = substr($serverInfo, stripos($serverInfo, 'uptime: ') + 8);
        $uptime = substr($tmp, 0, stripos($tmp, ' '));
        if ($uptime < \Cache::get('mysqlUptime')) {
            $supervisor->restart();
        }
        \Cache::put('mysqlUptime', $uptime, 60 * 24);
    }
}