<?php

namespace app\frontend\modules\withdraw\services\withdrawButton;

use app\common\facades\Setting;
use app\common\models\MemberShopInfo;
use app\frontend\modules\member\models\MemberBankCard;

class Manual extends DefaultWithdrawButton
{
    protected $value = 'manual';

    public function isEnabled(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return $this->withdraw() . '到' . (Setting::get('shop.lang.zh_cn.income.manual_withdrawal') ? : '手动打款');
    }

    public function getOtherName(): string
    {
        return Setting::get('shop.lang.zh_cn.income.manual_withdrawal') ? : '手动打款';
    }

    public function getTips(): string
    {
        switch ($this->getManualType()) {
            case 1:
                return '通过审核后将由工作人员打款到您的银行卡!';
            case 2:
                return '通过审核后将由工作人员打款到您的微信! ';
            case 3:
                return '通过审核后将由工作人员打款到您的支付宝!';
            default:
                return '';
        }
    }

    public function getIcon(): string
    {
        return 'iconfont icon-pay_otherpay';
    }

    public function getTitle(): string
    {
        switch ($this->getManualType()) {
            case 1:
                $type_name = '银行卡';break;
            case 2:
                $type_name = '微信';break;
            case 3:
                $type_name = '支付宝';break;
            default:
                $type_name = '';break;
        }
        return $this->getName() . '-' . $type_name;
    }

    public function getOtherData(): array
    {
        $member_id = \YunShop::app()->getMemberId();
        $yz_member = MemberShopInfo::uniacid()->select(['alipay','wechat'])->where('member_id', $member_id)->first();
        $member_bank = MemberBankCard::uniacid()->select(['member_name','bank_card'])->where('member_id', $member_id)->first();
        return [
            'manual_type' => Setting::get('withdraw.income.manual_type'),
            'alipay' => $yz_member->alipay,
            'wechat' => $yz_member->wechat,
            'member_name' => $member_bank->member_name,
            'bank_card' => $member_bank->bank_card ? substr_replace($member_bank->bank_card,'******',6,-4): '',
        ];
    }

    private function getManualType()
    {
        return Setting::get('withdraw.income.manual_type');
    }
}