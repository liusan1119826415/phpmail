<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/7/31
 * Time: 16:37
 */

namespace app\process;


use app\common\events\MqttTopicMessageEvent;
use app\common\events\MqttTopicMessageImmediatelyEvent;
use app\Jobs\MqttTopicMessageJob;
use Illuminate\Support\Facades\Redis;
use app\worker\Worker;
use Workerman\Connection\TcpConnection;
use app\common\modules\shop\ShopConfig;
use Workerman\Timer;

class MqttKeeper
{

    //tcp://123.232.42.162
    //port 4583
    //user：mqttscy
    //pwd：Scy2024.

    /**
     * @var Worker
     */
    protected $worker;

    protected $mqttConfig;


    protected $reconnect_limit = 12; //尝试重连次数

    public function __construct(Worker $worker,$setting = [])
    {
        $this->worker = $worker;
        $this->mqttConfig = $setting;
    }

    //设置记录 MQTT 连接状态
    public function recordState($state, $second = 300)
    {
        Redis::SETEX('mqtt_connect_state',$second, $state);
    }

    /**
     * 返回商城订阅的主题
     * @return array|mixed
     */
    public function getTopics()
    {
        $topics = ShopConfig::current()->get('mqtt.topic');

        $topics = collect($topics)->collapse()->all();

        $group = [];
        foreach ($topics as $topic => $qos) {
            $topicArray = explode('/',$topic);

            if ($topicArray[0] == 'plugin') {
                $group[$topicArray[1]][] = ['topic'=>$topic, 'qos' => $qos];
            } else  {
                $group['shop'][] = ['topic'=>$topic, 'qos' => $qos];
            }
        }

        $array = [];
        $uniAccount = \app\common\models\UniAccount::getEnable();
        foreach ($uniAccount as $u) {
            $enabledPlugins = app('plugins')->getEnabledPlugins($u->uniacid)->keys()->all();
            foreach ($group as $groupName => $groupTopics) {
                if ($groupName == 'shop' || in_array($groupName,$enabledPlugins)) {
                    foreach ($groupTopics as  $topics) {
                        $uniacidPluginTopic = rtrim($topics['topic'],'/').'/'.$u->uniacid;
                        $array[$uniacidPluginTopic] = $topics['qos'];
                    }
                }

            }
        }

        return $array;
    }

    /**
     * this is Worker  onWorkerStart method
     * @param Worker $worker
     * @throws \Exception
     */
    public function onWorkerStart(Worker $worker)
    {
        app('redis')->refresh();
        app('db')->refresh();

        //定时器
        Timer::add(5, function () use ($worker) {
            if (Redis::exists('MqttRunning')) {
                Redis::expire('MqttRunning', 10);
            } else {
                $worker->stop();
            }
        });

        //连接失败尝试重新连接次数记录
        Redis::setex('mqtt_reconnect_limit',120,0);

        $this->recordState(0);

        $topics = $this->getTopics();

        $wei = strripos($this->mqttConfig['address'],'/');
        $address = substr($this->mqttConfig['address'],$wei===false?0:$wei+1);

        $options = [
            'reconnect_period' => 5,//重连时间间隔秒
            'debug' => false,
        ];

        if ($this->mqttConfig['username'] && $this->mqttConfig['password']) {
            $options['username'] = $this->mqttConfig['username'];
            $options['password'] = $this->mqttConfig['password'];
        }

        $mqtt = new \Workerman\Mqtt\Client('mqtt://'.$address, $options);

        //\Log::info('-----MQTT订阅消息订阅主题：',$topics);
        //链接成功
        $mqtt->onConnect = function($mqtt) use ($topics) {

            \Log::info('-----MQTT订阅消息通信成功---->', $topics);

            $this->recordState(200);

            if ($topics) {
                //订阅主题
                $mqtt->subscribe($topics,[],function ($exception,$granted) {
                    //出现错误
                    if (!is_null($exception)) {
                        \Log::error("Mqtt client subscribe error", $exception);
                    }
                });
            } else {
                \Log::info('---MQTT订阅消息通信，没有设置订阅主题---->');
            }
        };
        //收到订阅消息 $topic为订阅主题
        $mqtt->onMessage = function($topic, $content) use ($mqtt) {
            \Log::info('<---Mqtt client receive message：', [$topic,$content]);

            //todo 同步可能会有问题，并发公众号会串问题

            //队列处理
            dispatch(new MqttTopicMessageJob($topic,$content));

        };

        //当连接发生某种错误时触发
        $mqtt->onError = function(\Exception $e) use ($mqtt) {

            $num = Redis::incr('mqtt_reconnect_limit');

            \Log::error("Mqtt client: {$e->getCode()}--{$e->getMessage()}--reconnect={$num}");

            if ($num > $this->reconnect_limit) {

                //尝试重连多次失败关闭连接
                $mqtt->close();
                Redis::del('mqtt_connect_state');
            }

        };

        //当连接关闭时触发
        $mqtt->onClose = function(\Workerman\Mqtt\Client $mqtt) {
            $this->recordState(500);
        };

        $mqtt->connect();

        //延长MQTT状态的有效期
        Timer::add(240, function () {

            if (Redis::exists('mqtt_connect_state')) {
                Redis::expire('mqtt_connect_state',300);
            }
        });
    }

    /**
     * @param Worker $worker
     */
    public function onWorkerStop(Worker $worker)
    {
        \Log::info('-----MQTT订阅消息链接关闭--->');
        $this->recordState(0);
        Redis::del('mqtt_reconnect_limit');
    }



    //之后可能会用，启用发生消息内部通道
    public function pushPending(Worker $worker)
    {
        var_dump('mqtt push message');
        // 启动时顺便启动内部通信，并连接监听内部通讯
        $inner_worker = new \app\worker\Worker('Text://127.0.0.1:6789');
        $inner_worker->onMessage = function (TcpConnection $tcpConnection, $data) use ($worker) {
            $data = json_decode($data, true);

            $wei = strripos($this->mqttConfig['address'],'/');
            $address = substr($this->mqttConfig['address'],$wei===false?0:$wei+1);

            $options = [
                'reconnect_period' => 5,//重连时间间隔秒
                'debug' => false,
            ];

            if ($this->mqttConfig['username'] && $this->mqttConfig['password']) {
                $options['username'] = $this->mqttConfig['username'];
                $options['password'] = $this->mqttConfig['password'];
            }

            $mqtt = new \Workerman\Mqtt\Client('mqtt://'.$address, $options);

            //链接成功
            $mqtt->onConnect = function($mqtt) use ($data,$tcpConnection) {
                $topic = $data['topic'];
                $mqtt->publish($topic, json_encode($data));
                $mqtt->disconnect();

                $tcpConnection->send('success');
            };

            $mqtt->connect();

            //当连接发生某种错误时触发
            $mqtt->onError = function(\Exception $e) use ($mqtt,$tcpConnection) {
                \Log::error("Mqtt client: {$e->getCode()}--{$e->getMessage()}");
                $mqtt->close();
                $tcpConnection->send('error');
            };

            $mqtt->onClose = function () {
                \Log::error("Mqtt client: 断开连接");
            };
        };

        $inner_worker->listen();

    }


    /**
     * 测试连接
     * @param Worker $worker
     * @throws \Exception
     */
    public function test(Worker $worker)
    {
        app('redis')->refresh();
        app('db')->refresh();

        //连接失败尝试重新连接次数记录
        Redis::setex('mqtt_reconnect_limit',120,0);
        $this->recordState(0);

        $wei = strripos($this->mqttConfig['address'],'/');
        $address = substr($this->mqttConfig['address'],$wei===false?0:$wei+1);

        $options = [
            'reconnect_period' => 5,//重连时间间隔秒
            'debug' => true,
        ];

        if ($this->mqttConfig['username'] && $this->mqttConfig['password']) {
            $options['username'] = $this->mqttConfig['username'];
            $options['password'] = $this->mqttConfig['password'];
        }


        $mqtt = new \Workerman\Mqtt\Client('mqtt://'.$address, $options);

        $topics = [
            'test/topic/1' => 0,
            'test/topic/2' => 0,
            'test/topic/3' => 0,
        ];

        //链接成功
        $mqtt->onConnect = function($mqtt) use ($topics) {

            $this->recordState(200);
            //订阅主题
            $mqtt->subscribe($topics,[],function ($exception, $granted) {
                //出现错误
                if (!is_null($exception)) {
                    echo 'error';
                    // \Log::info('<----mqtt----', $exception);
                }
            });
        };

        //收到订阅消息 $topic为订阅主题
        $mqtt->onMessage = function($topic, $content) use ($mqtt) {
            //\Log::info('<---Mqtt client receive message：', [$topic,$content]);
            try {
                //同步监听
                event($event = new MqttTopicMessageImmediatelyEvent($topic,$content));
                $messages = $event->getPushTopic();

                if ($messages) {
                    foreach ($messages as $t => $data) {
                        $mqtt->publish($t, json_encode($data,JSON_UNESCAPED_UNICODE));
                    }
                }

                echo 'message'.PHP_EOL;
            }catch (\Exception $exception){
                //\Log::error('MQTT订阅消息['.$topic.']同步监听异常',$exception);
                echo 'error'.$exception->getMessage().PHP_EOL;
            }

        };

        //当连接关闭时触发
        $mqtt->onClose = function(\Workerman\Mqtt\Client $mqtt){
            //$mqtt->close();
            $this->recordState(500);
            echo 'close1',PHP_EOL;
        };


        //当连接发生某种错误时触发
        $mqtt->onError = function(\Exception $e) use ($mqtt) {

            $num = Redis::incr('mqtt_reconnect_limit');

            echo "{$this->reconnect_limit}--Mqtt client: {$e->getCode()}--{$e->getMessage()}={$num}".PHP_EOL;
            if ($num > $this->reconnect_limit) {
                $mqtt->close();
            }
        };

        $mqtt->connect();

        Timer::add(20, function () {
            if (Redis::exists('mqtt_connect_state')) {
                Redis::expire('mqtt_connect_state',300);
            }
        });

    }

}
