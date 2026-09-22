<?php

namespace app\exports\action;


use app\backend\modules\member\models\MemberBankCard;
use app\backend\modules\member\models\MemberShopInfo;
use app\backend\modules\withdraw\models\WithdrawModel as Withdraw;
use app\common\models\WithdrawMergeServicetaxRate;

class WithdrawRecord extends BaseAction
{
    public $type = 'withdrawRecord';

    public $name = '提现记录';


    public function getData($request): \Generator
    {
        $records = \app\backend\modules\withdraw\models\WithdrawModel::records();
        $activeName = $request['search']['activeName'];
        if ($activeName && $activeName != 'all') {
            $records = $records->$activeName();
        }
        $search = $request->search;
        if ($search) {
            $search['searchtime'] = is_numeric($search['time']['start']) && is_numeric($search['time']['end']);
            $records->search($search);
        }
        $records->orderBy('id', 'desc');
        foreach ($records->get() as $key => $item) {
            $nickname = $item->hasOneMember->nickname;
            $realname = $item->hasOneMember->realname . '/' . $item->hasOneMember->mobile;
            $export_data[$key] = [
                'withdraw_sn' => $item->withdraw_sn,
                'member_id' => $item->member_id,
                'nickname' => strpos($nickname, '=') === 0 ? ' ' . $nickname : $nickname,
                'name' => strpos($realname, '=') === 0 ? ' ' . $realname : $realname,
                'type_name' => $item->type_name,
                'pay_way_name' => $item->pay_way_name,
                'amounts' => $item->amounts,
                'actual_poundage' => $this->getEstimatePoundage($item),//$item->actual_poundage,
                'actual_servicetax' => ($item->type == 'balance' ? 0 : $this->getEstimateServiceTax($item)),//$item->actual_servicetax,
                'actual_amounts' => $this->getActualAmount($item),
                'created_at' => $item->created_at->toDateTimeString(),
                'audit_at' => $item->audit_at ? $item->audit_at->toDateTimeString() : '',
                'pay_at' => $item->pay_at ? $item->pay_at->toDateTimeString() : '',
                'arrival_at' => $item->arrival_at ? $item->arrival_at->toDateTimeString() : '',
                'custom_value' => $this->getCustomValue($item->member_id),
            ];
            if ($item->pay_way == \app\backend\modules\withdraw\models\WithdrawModel::WITHDRAW_WITH_MANUAL) {
                switch ($item->manual_type) {
                    case 2:
                        $export_data[$key]['manual_type'] = '微信';
                        $export_data[$key] = array_merge($export_data[$key], $this->getMemberWeChat($item->member_id));
                        break;
                    case 3:
                        $export_data[$key]['manual_type'] = '支付宝';
                        $export_data[$key] = array_merge($export_data[$key], $this->getMemberAlipay($item->member_id));
                        break;
                    default:
                        $export_data[$key]['manual_type'] = '银行卡';
                        $export_data[$key] = array_merge($export_data[$key], $this->getMemberBankCard($item->member_id));
                        break;
                }
            } else {
                switch ($item->pay_way) {
                    case Withdraw::WITHDRAW_WITH_WECHAT:
                        $export_data[$key]['manual_type'] = '';
                        $export_data[$key] = array_merge($export_data[$key], $this->getMemberWeChat($item->member_id));
                        break;
                    case Withdraw::WITHDRAW_WITH_ALIPAY:
                        $export_data[$key]['manual_type'] = '';
                        $export_data[$key] = array_merge($export_data[$key], $this->getMemberAlipay($item->member_id));
                        break;
                    case Withdraw::WITHDRAW_WITH_WORK_WITHDRAW_BANK:
                    case Withdraw::WITHDRAW_WITH_JIANZHIMAO_BANK:
                    case Withdraw::TAX_WITHDRAW_BANK:
                        $export_data[$key]['manual_type'] = '';
                        $export_data[$key] = array_merge($export_data[$key], $this->getMemberBankCard($item->member_id));
                        break;
                }
            }
            //判断字体，针对性的防止𠂆字，使xsl终止不完整的bug
            $zit = strpos($export_data[$key][21], '𠂆');
            if ($zit) {
                $export_data[$key][21] = '*';
            }
            yield $key => $export_data[$key];
        }
    }

    private function getMemberAlipay($member_id)
    {
        $yzMember = MemberShopInfo::select('alipayname', 'alipay')->where('member_id', $member_id)->first();
        return $yzMember ? ['wechat' => '', 'alipay_name' => $yzMember->alipayname ?: '', 'alipay_account' => $yzMember->alipay ?: ''] : ['', ''];
    }

    private function getMemberWeChat($member_id)
    {
        $yzMember = MemberShopInfo::select('wechat')->where('member_id', $member_id)->first();
        $wechat = $yzMember ? $yzMember->wechat ?: '' : '';
        return ['wechat' => $wechat];
    }

    private function getMemberBankCard($member_id)
    {
        $bankCard = MemberBankCard::where('member_id', $member_id)->first();
        if ($bankCard) {
            return [
                'wechat' => '',
                'alipay_name' => '',
                'alipay_account' => '',
                'bank_name' => $bankCard->bank_name ?: '',
                'bank_province' => $bankCard->bank_province ?: '',
                'bank_city' => $bankCard->bank_city ?: '',
                'bank_branch' => $bankCard->bank_branch ?: '',
                'bank_card' => $bankCard->bank_card ? $bankCard->bank_card . "\t" : '',
                'member_name' => $bankCard->member_name ?: '',
                'idcard' => $bankCard->idcard ? $bankCard->idcard . "\t" : '',
                'mobile' => $bankCard->mobile ? $bankCard->mobile . "\t" : '',
            ];
        }
        return [
            'wechat' => '',
            'alipay_name' => '',
            'alipay_account' => '',
            'bank_name' => '',
            'bank_province' => '',
            'bank_city' => '',
            'bank_branch' => '',
            'bank_card' => '',
            'member_name' => '',
            'idcard' => '',
            'mobile' => ''
        ];
    }

    private function getEstimatePoundage($item)
    {
        if (!(float)$item->actual_poundage > 0 || is_null($item->actual_poundage)) {
            return bcdiv(bcmul($item->amounts, $item->poundage_rate, 4), 100, 2);
        }
        return $item->actual_poundage;
    }

    private function getEstimateServiceTax($withdraw)
    {
//        if (!(float)$item->actual_servicetax > 0 || is_null($item->actual_servicetax)) {
//            $poundage = $this->getEstimatePoundage($item);
//            $amount = bcsub($item->amounts, $poundage, 2);
//            return bcdiv(bcmul($amount, $item->servicetax_rate, 4), 100, 2);
//        }
        $withdraw->servicetax = $this->setWithdraw($withdraw)->servicetax;

        return $withdraw->servicetax;
    }

    private function getCustomValue($member_id)
    {
        $yzMember = MemberShopInfo::select('member_form')->where('member_id', $member_id)->first();

        $str = '';
        if ($yzMember->member_form) {
            foreach (json_decode($yzMember->member_form) as $value) {
                $str .= '<' . $value->name . ':' . $value->value . '>     ';
            }
        }
        return $str;
    }

    public function getActualAmount($withdraw)
    {
        $withdraw_data = $this->setWithdraw($withdraw);
        if ($withdraw_data->type == 'balance') {//余额不减劳务税
            $withdraw_data->actual_amounts = bcsub($withdraw_data->amounts, $withdraw_data->poundage, 2);
        }

        // 暂时屏蔽, 等之后有误重新计算
//        else {
//            $withdraw_data->actual_amounts = bcsub(bcsub($withdraw_data->amounts, $withdraw_data->poundage, 2), $withdraw_data->servicetax, 2);
//        }
        return $withdraw_data->actual_amounts;
    }

    private function setWithdraw($withdraw)
    {
        if ($withdraw->status == 0) {
            $withdraw_set = \Setting::get('withdraw.income');
            if ($withdraw->pay_way == 'balance' && $withdraw_set['balance_special']) {
                $merge_percent = null;
            } else {
                $merge_percent = WithdrawMergeServicetaxRate::uniacid()->where('withdraw_id', $withdraw->id)->where('is_disabled', 0)->first();
            }
            if ($merge_percent) {
                $withdraw->servicetax_rate = $merge_percent->servicetax_rate;
                $base_amount = !$withdraw_set['service_tax_calculation'] ? bcsub($withdraw->amounts, $withdraw->poundage, 2) : $withdraw->amounts;
                $withdraw->servicetax = bcmul($base_amount, bcdiv($withdraw->servicetax_rate, 100, 4), 2);
            } elseif ($withdraw->pay_way != 'balance' || !$withdraw_set['balance_special']) {
                $base_amount = !$withdraw_set['service_tax_calculation'] ? bcsub($withdraw->amounts, $withdraw->poundage, 2) : $withdraw->amounts;
                $res = \app\common\services\finance\Withdraw::getWithdrawServicetaxPercent($base_amount, $withdraw);
                $withdraw->servicetax_rate = $res['servicetax_percent'];
                $withdraw->servicetax = $res['servicetax_amount'];
            }
        }
        return $withdraw;
    }


}
