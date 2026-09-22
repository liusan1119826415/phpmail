<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/8/2
 * Time: 15:08
 */

namespace app\process\models;


class MqttModel
{
    public static function analysisTopic($topic)
    {
        $topicArray = explode('/',$topic);
        $i = array_pop($topicArray);

        return [
            'i' => $i,
            'without_i_topic' => implode($topicArray,'/'),
        ];
    }

    public static function sendMessage($topic,$data, $qos = 0)
    {

        $setting = \app\common\facades\SiteSetting::get('mqtt');


        if ($setting['is_switch'] != 1 || empty($setting['address'])) {
            return ['state' => 0, 'msg' => '未开启MQTT配置'];
        }


        $wei = strripos($setting['address'],'/');
        $address = substr($setting['address'],$wei===false?0:$wei+1);
        $address = explode(':',$address);

        // $id 客户端ID,如果省略或者为null,会随机生成一个。
        // $cleanSession 设为false断开保留、true断开删除订阅和消息
        $client = new \Mosquitto\Client();

        if ($setting['username'] && $setting['password']) {
            //设置连接到服务器的用户名和密码
            $client->setCredentials($setting['username'], $setting['password']);
        }

        //连接成功回调，发生主题消息
        $client->onConnect(function ($rc, $message) use ($client,$topic,$data,$qos) {

            if ($rc == 0) {
                //发布主题消息
                $client->publish($topic, json_encode($data), $qos);
            } else {
                \Log::debug("Connect failed: $rc - $message");
            }

        });

        //当客户端自己发布消息时调用，在 publish 之后触发
        $client->onPublish(function ($mid) use ($client) {
            // 断开连接
            $client->disconnect();
        });

        //收到从服务器返回的消息时调用
        $client->onMessage(function ($message) use ($client) {

        });

        // 连接到MQTT代理服务器
        $client->connect($address[0], $address[1], 60);

        //一直运行直到客户端断开连接
        $client->loopForever();


        return ['state' => 1, 'msg' => '消息发送成功'];
    }

}