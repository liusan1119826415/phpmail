<?php

namespace app\frontend\controllers;

use app\common\components\BaseController;
use app\common\facades\RichText;
use app\common\facades\Setting;
use app\common\models\Protocol;

class PrivacyPolicyController extends BaseController
{
    public function index()
	{
		$shop_setting = Setting::get('shop.shop');
        //注册协议
        $registerProtocol=Protocol::uniacid()->where('status',1)->select(['id','content','title','default_tick'])->first();
        if($registerProtocol&&!$registerProtocol->title){
            $registerProtocol->title='会员注册协议';
        }
        //平台协议
        $platformProtocol= ['title'=>$shop_setting['agreement_name']?:'平台协议','content'=> RichText::get('shop.agreement')];
        $data = [
            'shop_name' => $shop_setting['name'],
            'register_protocol' => $registerProtocol?:false,
            'platform_protocol'=>  $shop_setting['is_agreement']?$platformProtocol:false,
            'shop_logo' =>yz_tomedia($shop_setting['logo']),
            'theme_color'=>$shop_setting['theme_color']
		];

		return $this->successJson('ok', $data);
	}
}