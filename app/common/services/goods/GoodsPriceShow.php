<?php

namespace app\common\services\goods;

class GoodsPriceShow
{
    protected $plugin_id;
    protected $member_id;
    protected $goodsModel;

    public function __construct($plugin_id,$member_id,$goodsModel)
    {
        $this->plugin_id = $plugin_id;
        $this->member_id = $member_id;
        $this->goodsModel = $goodsModel;
    }

    public function getVipLevelStatus(): array
    {
        $vip_level_status = [
            'status'  => 0,//0-原规则，1-无权限显示
            'word' => '',
            'tips' => ''
        ];

        if (app('plugins')->isEnabled('user-permissions-set')) {
            $vip_level_status = (new \Yunshop\UserPermissionsSet\common\services\GoodsPricePrivilegeService($this->plugin_id,$this->member_id,$this->goodsModel))->getPluginData();
        }

        return $vip_level_status;
    }
}