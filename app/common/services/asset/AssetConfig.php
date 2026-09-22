<?php

namespace app\common\services\asset;

class AssetConfig
{
    public function getAssetConfig(): array
    {
        return [
            'income' => [],
            'balance' => [
                147 => function () {
                    return ['name' => '接口传入'];
                }
            ],
            'point' => [
                180 => function () {
                    return ['name' => '收入提现扣除'];
                },
                188 => function () {
                    return ['name' => '收入提现扣除返还'];
                },
                192 => function () {
                    return ['name' => '绑定手机号赠送'];
                },
                193 => function () {
                    return ['name' => '绑定邮箱赠送'];
                },
                198 => function () {
                    return ['name' => '接口传入'];
                },
                212 => function () {
                    return ['name' => (\Setting::get('shop.lang.zh_cn.member_center.credit') ?: '余额') . '转账奖励'];
                },
            ],
        ];
    }
}
