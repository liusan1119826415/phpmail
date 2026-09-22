<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/8/2
 * Time: 15:04
 */

namespace app\common\events;


use app\process\models\MqttModel;

class MqttTopicMessageImmediatelyEvent extends Event
{

    protected $topic;

    protected $content;

    protected $originalTopic;

    protected $pushMessage = [];

    public function __construct($topic,$content)
    {
        $topicArray = MqttModel::analysisTopic($topic);

        \YunShop::app()->uniacid = $topicArray['i'];
        \Setting::$uniqueAccountId = $topicArray['i'];

        $this->topic = $topic;
        $this->content = $content;

        $topic->originalTopic = $topicArray['without_i_topic'];
    }

    public function pushTopic(string $topic,$data)
    {
        $this->pushMessage[$topic] = $data;
    }

    public function getPushTopic()
    {
        return $this->pushMessage;
    }

    public function getTopic()
    {
        return $this->topic;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function getWithoutITopic()
    {
        return $this->originalTopic;
    }
}