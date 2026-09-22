<?php

namespace app\exports\action;

class Income extends BaseAction
{
    public $type = 'income';

    public $name = '收入';


    public function getData($request): \Generator
    {
        $records = \app\backend\modules\income\models\Income::records()->withMember()
            ->with([
                'hasManyOrder' => function ($query) {
                    $query->select('order_sn', 'price');
                }
            ])
            ->orderBy('id', 'desc');
        $search = $request->search;
        if ($search) {
            $records = $records->search($search)->searchMember($search);
        }
        foreach ($records->get() as $k => $v) {
            yield $k => [
                'member_id' => $v['member_id'],
                'nickname' => empty($v['member']['nickname']) ? '' : $v['member']['nickname'],
                'name' => empty($v['member']['realname']) ? '' : $v['member']['realname'],
                'mobile' => empty($v['member']['mobile']) ? '' : $v['member']['mobile'],
                'time' => $v['created_at'],
                'money' => $v['amount'],
                'type' => $v['type_name'],
                'withdraw_status' => $v['status_name'],
                'pay_status' => $v['pay_status_name'],
                'order_sn' => ($v['order_sn'] ?: ''),
                'order_price' => ($v['hasManyOrder']['price'] ?: ''),
            ];
        }
    }


}
