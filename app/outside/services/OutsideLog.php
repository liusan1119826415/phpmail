<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/4/29
 * Time: 11:17
 */

namespace app\outside\services;

use app\framework\Log\BaseLog;

class OutsideLog extends BaseLog
{
    protected $logDir = 'logs/outside/info.log';
    public function add($message, array $content = [])
    {
        $this->log->info($message, $content);
    }


    public static function info($message, $content = [])
    {
        (new OutsideLog())->add($message,$content);
    }
}
