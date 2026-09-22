<?php

namespace app\console\Commands;

use app\common\facades\SiteSetting;
use app\host\HostManager;
use app\process\CronKeeper;
use app\process\ExportKeeper;
use app\process\MqttKeeper;
use app\process\QueueKeeper;
use app\process\WebSocket;
use app\worker\Worker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Timer;

class Shop extends Command
{
    protected $signature = 'shop {action} {--d}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '商城守护进程';

    public function handle()
    {
        global $argv;
        $action = $this->argument('action');

        $argv[0] = 'wk';
        $argv[1] = $action;
        $argv[2] = $this->option('d') ? '-d' : '';

        if ($action == 'start') {
            $this->start();
        }
        if ($action == 'stop') {
        }
        // 运行worker
        Worker::runAll();
    }

    private $startTime;

    /**
     * 队列进程管理
     */
    private function queueKeeper()
    {
        $worker = new Worker();
        $worker->count = 1;
        $worker->name = 'yun_shop_queue';
        app()->forgetInstance('redis');
        app()->forgetInstance('db');
        // 生成队列进程
        $worker->onWorkerStart = function ($worker) {
            // 避免多个服务器的work同一时间生成,产生不有必要的并发问题
            sleep(rand(0, 100) / 100);

            error_reporting(0);
            ini_set('display_errors', 1);
            app('redis')->refresh();
            app('db')->refresh();
            (new HostManager())->localhost()->clearPids();
            $queueKeeper = new QueueKeeper();
            $queueKeeper->main();

            $queueTimer = function () use ($queueKeeper) {
                $hostManager = new HostManager();

                $hostManager->localhost()->clearPids();
                $hostManager->localhost()->register();
                $queueKeeper->keepAlive();
            };
            call_user_func($queueTimer);
            Timer::add(30, $queueTimer);

            $worker->queueKeeper = $queueKeeper;
        };
        // 关闭队列进程
        $worker->onWorkerStop = function ($worker) {
            error_reporting(0);
            ini_set('display_errors', 1);
            Timer::delAll();
            $worker->queueKeeper->stop();
            $hostManager = new HostManager();
            $hostManager->localhost()->clearPids();
            $hostManager->localhost()->killAll();
            $hostManager->logout(gethostname());
        };
    }

    private function daemonKeeper()
    {
        $worker = new Worker();
        $worker->count = 1;
        $worker->name = 'yun_shop_daemon';
        app()->forgetInstance('redis');
        app()->forgetInstance('db');
        $worker->onWorkerStart = function ($worker) {
            sleep(rand(0, 100) / 100);

            error_reporting(0);
            ini_set('display_errors', 1);
            app('redis')->refresh();
            app('db')->refresh();
            Timer::add(1, function () {
                $hostManager = new HostManager();
                // 升级后延时重启队列（防止更新后无重启队列）
                //判断重启时间,如果60秒内重启过就不重启
//                if (($hostManager->restartTime() > $this->startTime) && ($this->startTime + 60 < $hostManager->restartTime())) {
                if (($hostManager->restartTime() > $this->startTime)) {
                    app('supervisor')->restart();
                }
            });
        };
        // 关闭队列进程
        $worker->onWorkerStop = function ($worker) {
            error_reporting(0);
            ini_set('display_errors', 1);
            Timer::delAll();
        };

    }

    private function cronKeeper()
    {
        //验证第一台服务器



        $worker = new Worker();

        $worker->count = 1;
        $worker->name = 'yun_shop_cron';

        // 生成队列进程
        $worker->onWorkerStart = function ($worker) {
            // 避免多个服务器的work同一时间生成,产生不有必要的并发问题
            error_reporting(0);
            ini_set('display_errors', 1);
            app('redis')->refresh();
            app('db')->refresh();
            $cronKeeper = new CronKeeper();
            $cronKeeper->main();
            //限制定时任务在00秒的时候开始
            while (true) {
                sleep(rand(0, 100) / 10);
                if (date('s') < 20) {
                    break;
                }
            }
            Timer::add(60, function () use ($cronKeeper) {
                $cronKeeper->run();
            });

        };
        // 关闭队列进程
        $worker->onWorkerStop = function ($worker) {
            error_reporting(0);
            ini_set('display_errors', 1);
            Timer::delAll();
            Redis::del('RunningCronJobs');
        };
    }

    private function webSocketKeeper()
    {
        if (SiteSetting::get('websocket')['is_open'] != 1) {
            return;
        }
        $worker = new Worker('websocket://0.0.0.0:8181');
        $worker->count = 1;
        $webSocket = new WebSocket($worker);
        // 连接时回调
        $worker->onConnect = [$webSocket, 'onConnect'];
        // 收到客户端信息时回调
        $worker->onMessage = [$webSocket, 'onMessage'];
        // 进程启动后的回调
        $worker->onWorkerStart = [$webSocket, 'onWorkerStart'];
        // 断开时触发的回调
        $worker->onClose = [$webSocket, 'onClose'];
    }
    /**
     *   拦截中台消息直接写入yz-supply logs文件
     *   2023/11/25 10:34
     */
    private function workerManYzSupply()
    {
        //默认关闭
        if (SiteSetting::get('yz_supply_message')['is_open'] != 1 ) {
            return;
        }
        $port = 8282;
        if (SiteSetting::get('yz_supply_message')["port"] != ""){
            $port =  (int)SiteSetting::get('yz_supply_message')["port"];
        }
        $worker = new Worker('http://0.0.0.0:'.$port.'');
        $worker->count = 1;
        $worker->name = 'yz_supply_message_logs';
        $worker->onMessage = function(TcpConnection $connection, Request $request)
        {
            $content = $request->rawBody(); //获取内容
            $i = $request->get("i"); //获取平台id

            $date = date('YmdHi',time());
            $now_path = dirname(dirname(dirname(dirname(__FILE__))));
            $now_path = $now_path."/plugins/yz-supply";
            //如果不存在供应链文件直接返回不需要写入
            if (file_exists($now_path) == true){
                //开始写入logs内容
                $save_path = $now_path.'/logs';
                if (!is_dir($save_path)) {
                    mkdir($save_path);
                }
                $save_path = $save_path.'/'.$i;
                if (!is_dir($save_path)) {
                    mkdir($save_path);
                }
                $path = $save_path.'/'.$date.'.log';
                if (file_exists($path)) {
                    @$file = fopen($path,'a');
                    fwrite($file, "--\r\n" . $content);
                    fclose($file);
                } else {
                    @$file = fopen($path,'w');
                    fwrite($file,$content);
                    fclose($file);
                }
            }
            $response = new Response(200, [
                'Content-Type' => 'application/json',
            ], json_encode(["code"=>0]));
            $connection->send($response);
        };

    }

    private function exportKeeper()
    {
        $worker = new Worker();
        $worker->count = 1;
        $worker->name = 'yun_shop_export';
        // 生成队列进程
        $worker->onWorkerStart = function ($worker) {
            // 避免多个服务器的work同一时间生成,产生不有必要的并发问题
            sleep(rand(0, 100) / 100);
            error_reporting(0);
            ini_set('display_errors', 1);
            app('redis')->refresh();
            app('db')->refresh();
            (new ExportKeeper())->handle($worker);
        };
        // 关闭队列进程
        $worker->onWorkerStop = function ($worker) {
            error_reporting(0);
            ini_set('display_errors', 1);
        };
    }

    private function mqttKeeper()
    {
        $setting = SiteSetting::get('mqtt');

        if ($setting['is_switch'] != 1 || empty($setting['address'])) {
            return;
        }

        //\Log::info('<-----MQTT订阅消息开始',$setting);

        $worker = new Worker();
        $worker->name = 'yun_shop_mqtt';

        $mqttKeeper = new MqttKeeper($worker,$setting);

        $worker->onWorkerStart = function ($worker) use ($mqttKeeper) {
            //如果没抢到锁
            if (!Redis::setnx('MqttRunning', gethostname() . '[' . getmypid() . ']')) {
                //增加监听，当执行mqtt服务器宕机时接管mqtt服务
                Timer::add(10, function () use ($worker,$mqttKeeper) {
                    if (!Redis::setnx('MqttRunning', gethostname() . '[' . getmypid() . ']')) {
                        //抢到锁执行启动逻辑
                        Redis::expire('MqttRunning', 10);
                        $mqttKeeper->onWorkerStart($worker);
                    }
                });
            } else {
                //启动mqtt服务
                Redis::expire('MqttRunning', 10);
                $mqttKeeper->onWorkerStart($worker);
            }
        };
        // 关闭队列进程
        $worker->onWorkerStop = [$mqttKeeper, 'onWorkerStop'];
    }


    private function start()
    {
        // 记录自身host

        (new HostManager())->localhost()->register();
        $this->startTime = time();
        $this->daemonKeeper();
        $this->queueKeeper();
        $this->cronKeeper();
        $this->webSocketKeeper();
        $this->workerManYzSupply();
        //导出
        $this->exportKeeper();

        //MQTT订阅消息进程
        $this->mqttKeeper();
    }
}
