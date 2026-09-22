<?php

namespace app\common\events\systemMsg;

use app\common\events\Event;
use app\common\models\systemMsg\SysMsgLog;

/**
 * 商城信息发送后事件
 */
class AfterSendSysMsgEvent extends Event
{
    protected $sysMsgLogModel;

    public function __construct(SysMsgLog $sysMsgLogModel)
    {
        $this->setSysMsgLogModel($sysMsgLogModel);
    }

    /**
     * @return SysMsgLog
     */
    public function getSysMsgLogModel(): SysMsgLog
    {
        return $this->sysMsgLogModel;
    }

    /**
     * @param SysMsgLog $sysMsgLogModel
     * @return void
     */
    protected function setSysMsgLogModel(SysMsgLog $sysMsgLogModel): void
    {
        $this->sysMsgLogModel = $sysMsgLogModel;
    }

}
