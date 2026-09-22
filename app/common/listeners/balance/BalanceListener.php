<?php
/**
 * Created by PhpStorm.
 * User: LiBaoJia
 * Date: 2018/1/17
 * Time: 16:03
 */

namespace app\common\listeners\balance;


use app\common\events\member\MemberBalanceChangeEvent;
use app\common\events\order\AfterOrderReceivedEvent;
use app\common\services\finance\BalanceAwardService;
use app\common\services\finance\TransferIncreasePointService;

/**
 * 余额监听者
 * Class BalanceListener
 * @package app\common\listeners\balance
 */
class BalanceListener
{
    public function subscribe($events)
    {
        $events->listen(AfterOrderReceivedEvent::class, BalanceAwardService::class . '@awardBalance');

        /**
         * 转账扣除手续费奖励积分
         */
        $events->listen(MemberBalanceChangeEvent::class, TransferIncreasePointService::class . '@rewardPoint');
    }
}
