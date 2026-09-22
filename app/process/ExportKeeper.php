<?php

namespace app\process;

use app\backend\modules\export\models\ExportRecord;

class ExportKeeper
{
    public function handle($worker)
    {

        ini_set('memory_limit', -1);
        while (true) {
            $job = ExportRecord::withoutGlobalScope('uniacid')->where('status', 0)->orderby('id', 'asc')->first();
            if ($job) {
                \Log::debug('导出任务守护进程开始', [$job]);
                //执行任务
                try {
                    \Log::debug('导出任务守护进程内存1',[memory_get_usage()/1024/1024,memory_get_usage(true)/1024/1024]);
                    var_dump('导出任务守护进程内存1',memory_get_usage()/1024/1024,memory_get_usage(true)/1024/1024);
                    app('Export')->execute($job);
                    var_dump('导出任务守护进程内存2',memory_get_usage()/1024/1024,memory_get_usage(true)/1024/1024);
                } catch (\Exception $e) {
                    \Log::debug('导出任务失败：' . $e->getMessage(), [$job['id']]);
                    $job->status = 2;
                    $job->save();
                }
                $worker->stopAll();
            } else {
                sleep(3);
            }
        }
    }
}
