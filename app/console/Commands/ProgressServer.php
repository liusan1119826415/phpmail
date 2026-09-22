<?php

namespace app\Console\Commands;



use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Workerman\Worker;
use Yunshop\AiDesign\services\UtilsService;


class ProgressServer extends Command
{


    protected $signature = 'progress {action} {--d}';
    protected $description = 'Start the Workerman WebSocket server';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {

        $wsWorker = new Worker("websocket://0.0.0.0:2346");
        $wsWorker->clients = [];

        $wsWorker->onWorkerStart = function () use ($wsWorker) {
            $wsWorker->timerId = null; // 用于存储定时器 ID

            // 当有客户端连接时
            $wsWorker->onConnect = function ($connection) use ($wsWorker) {
                $wsWorker->clients[$connection->id] = $connection;

                // 如果定时器未启动，则启动定时器
                if ($wsWorker->timerId === null) {
                    $wsWorker->timerId = \Workerman\Timer::add(1, function () use ($wsWorker) {
//                        static $isProcessing = false;
//
//                        // 防止重复执行
//                        if ($isProcessing) {
//                            return;
//                        }
//                        $isProcessing = true;

                        foreach ($wsWorker->clients as $client) {
                            if (isset($client->task_id)) {
                                //\Log::debug("Processing client with task_id: {$client->task_id}");

                                $utilsService = new UtilsService();
                                $request_data = ['taskId' => $client->task_id, 'task_type' => $client->task_type];
                                $class_obj = $utilsService->getClassInstance($request_data);
                                $progressData = $class_obj->getProgress();

                                // 发送进度更新
                         
                                    $client->send(json_encode([
                                        'status' => 'ok',
                                        'task_id' => $client->task_id,
                                        'progress' => $progressData,
                                    ]));



                                // 检查进度是否达到 100%
                                if ($progressData['progress'] == 100 && $progressData['piclist']) {
                                    unset($wsWorker->clients[$client->id]);
                                    $client->close();
                                   // \Log::debug("Client with task_id {$client->task_id} completed and disconnected.");
                                }
                            }
                        }

                        // 如果没有活跃客户端连接，则删除定时器
                        if (empty($wsWorker->clients) && $wsWorker->timerId !== null) {
                            \Workerman\Timer::del($wsWorker->timerId);
                            $wsWorker->timerId = null;
                           // \Log::debug("All clients completed. Timer deleted.");
                        }

                      //  $isProcessing = false;
                    });
                }

                // 处理来自客户端的消息
                $connection->onMessage = function ($connection, $data) {
                    $decodedData = json_decode($data, true);
                    if (isset($decodedData['task_id'])) {
                        $connection->task_id = $decodedData['task_id'];
                        $connection->task_type = $decodedData['task_type'];
                    }
                };
            };

            // 当客户端断开连接时
            $wsWorker->onClose = function ($connection) use ($wsWorker) {
                unset($wsWorker->clients[$connection->id]);

                // 如果没有客户端，删除定时器
                if (empty($wsWorker->clients) && $wsWorker->timerId !== null) {
                    \Workerman\Timer::del($wsWorker->timerId);
                    $wsWorker->timerId = null;
                   // \Log::debug("Timer deleted because no clients are connected.");
                }
            };
        };

        Worker::runAll();

    }
}
