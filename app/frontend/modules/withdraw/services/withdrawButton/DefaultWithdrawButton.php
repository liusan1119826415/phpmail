<?php

namespace app\frontend\modules\withdraw\services\withdrawButton;

use app\common\facades\Setting;

class DefaultWithdrawButton extends BaseWithdrawButton
{
    protected $value = '';

    public function isEnabled(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return '';
    }

    public function getOtherName(): string
    {
        return '';
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getExtraData(): array
    {
        $return_data = [];
        if (!is_null($data = \app\common\modules\shop\ShopConfig::current()->get('withdraw_list_extra_data.' . $this->getValue()))) {
            if (($class = $data['class']) && ($function = $data['function']) && method_exists($class, $function)) {
                $return_data = $class::$function();
            }
        }
        return $return_data;
    }

    public function getTips(): string
    {
        return '';
    }

    public function getIcon(): string
    {
        return '';
    }

    public function getTitle(): string
    {
        return '';
    }

    public function getOtherData(): array
    {
        return [];
    }
}