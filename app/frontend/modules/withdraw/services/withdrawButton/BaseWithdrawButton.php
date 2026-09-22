<?php

namespace app\frontend\modules\withdraw\services\withdrawButton;

use app\common\facades\Setting;

abstract class BaseWithdrawButton
{
    abstract function isEnabled(): bool;

    abstract function getName(): string;

    abstract function getOtherName(): string;

    abstract function getValue(): string;

    abstract function getExtraData(): array;

    abstract function getTips(): string;

    abstract function getIcon(): string;

    abstract function getTitle(): string;

    abstract function getOtherData(): array;

    protected function withdraw(): string
    {
        return (Setting::get('shop.lang.zh_cn')['income']['name_of_withdrawal'] ? : '提现');
    }
}