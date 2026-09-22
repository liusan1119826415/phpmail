<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/8/1
 * Time: 17:21
 */

namespace app\Jobs;

use app\common\events\MqttTopicMessageEvent;
use app\host\HostManager;
use app\process\models\MqttModel;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class MqttTopicMessageJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public $uniacid;

    public $topic;

    public $content;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($topic,$content)
    {
//        $this->queue = 'mqtt';

//        $hostCount = count((new HostManager())->hosts() ?: []) ? : 1;
//        $this->queue = 'limit:'.($uniacid % (3 * $hostCount));

        $topicArray = MqttModel::analysisTopic($topic);
        $this->uniacid = $topicArray['i'];
        $this->topic = $topic;
        $this->content = $content;
    }

    public function handle()
    {

        \Log::info('MQTT订阅消息['.$this->topic.']任务开始执行');
        DB::transaction(function () {
            try {
                \YunShop::app()->uniacid = $this->uniacid;
                \Setting::$uniqueAccountId = $this->uniacid;

                $event = new MqttTopicMessageEvent($this->topic,$this->content);
                app('events')->safeFire($event,$this->uniacid);
            }catch (\Exception $exception){
                \Log::error('MQTT订阅消息['.$this->topic.']任务异常',$exception);
                throw $exception;
            }

        });
        \Log::info('MQTT订阅消息['.$this->topic.']任务执行完成');

    }
}