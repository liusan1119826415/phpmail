<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2021/8/26
 * Time: 14:02
 */

namespace app\common\payment\method\other;


use app\common\models\Member;
use app\common\payment\method\BasePayment;
use app\common\payment\setting\other\BalanceSetting;

class BalancePayment extends BasePayment
{
	public $code = 'balance';

	public function __construct(BalanceSetting $paymentSetting)
	{
		$this->setSetting($paymentSetting);
	}

    public function getUsable()
    {
        if (\YunShop::app()->getMemberId() == 0) {
            return 0;
        }
        return Member::uniacid()->where('uid',\YunShop::app()->getMemberId())->value('credit2');
    }

    public function getOther()
    {
        return [
            'need_password' => $this->needPassword(),
            'usable' => $this->getUsable()
        ];
    }
}