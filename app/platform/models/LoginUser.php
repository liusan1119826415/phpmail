<?php

namespace app\platform\models;

use Illuminate\Support\Facades\DB;

class LoginUser
{
	public function getUserType($userId)
	{
		$mobile = '';

		if (\Schema::hasTable('yz_store'))

		{
			$member_id = DB::table('yz_store')->where('user_uid',$userId)->value('uid'); //门店
			$mobile = DB::table('yz_store_apply')->where('uid',$member_id)->value('mobile'); //门店
		}

		if (\Schema::hasTable('yz_hotel') && !$mobile) {
			$mobile = DB::table('yz_hotel')->where('user_uid',$userId)->value('mobile');       //酒店
		}

		if (\Schema::hasTable('yz_area_dividend_agent') && !$mobile) {
			$mobile = DB::table('yz_area_dividend_agent')->where('user_id',$userId)->value('mobile');       //区域分红
		}

		if (\Schema::hasTable('yz_supplier') && !$mobile) {
			$mobile = DB::table('yz_supplier')->where('uid',$userId)->value('mobile');       //供应商
		}

		if (\Schema::hasTable('yz_package_deliver') && !$mobile) {
			$mobile = DB::table('yz_package_deliver')->where('user_uid',$userId)->value('deliver_mobile');       //自提点
		}

		if (\Schema::hasTable('yz_subsidiary') && !$mobile) {
			$mobile = DB::table('yz_subsidiary')->where('user_uid',$userId)->value('mobile');       //分公司
		}

		if (\Schema::hasTable('yz_plugin_article_manager') && !$mobile) {
			$mobile = DB::table('yz_plugin_article_manager')->where('uid',$userId)->value('mobile');       //文章营销
		}

		return $mobile;
	}

    //验证部分独立后台登录状态
    public function validatorLoginSign($userId)
    {
        $res = '';

        if (\Schema::hasTable('yz_store'))
        {
            $res = DB::table('yz_store')->where('user_uid',$userId)->first();//门店
        }

        if (\Schema::hasTable('yz_hotel') && !$res) {
            $res = DB::table('yz_hotel')->where('user_uid',$userId)->first();//酒店
        }

        if (\Schema::hasTable('yz_supplier') && !$res) {
            $res = DB::table('yz_supplier')->where('uid',$userId)->first();//供应商
        }

        return $res;
    }
}