<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/8/1
 * Time: 17:28
 */

namespace app\common\events;

use app\common\events\Event;
use app\process\models\MqttModel;

class MqttTopicMessageEvent extends Event
{
    protected $topic;

    protected $content;

    protected $originalTopic;

    public function __construct($topic,$content)
    {
        $this->topic = $topic;
        $this->content = $content;

        $topicArray = MqttModel::analysisTopic($topic);
        $topic->originalTopic = $topicArray['without_i_topic'];
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