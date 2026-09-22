<?php


namespace app\common\cron;

use Illuminate\Foundation\Bus\DispatchesJobs;

class UpdateCache
{
    use DispatchesJobs;

    public function handle()
    {
        \Artisan::call('config:cache');
        \Cache::flush();
    }
}