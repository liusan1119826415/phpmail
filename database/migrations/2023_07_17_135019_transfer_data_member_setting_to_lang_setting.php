<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use app\common\facades\Setting;

class TransferDataMemberSettingToLangSetting extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('yz_setting')) {
            $uniAccount = \app\common\models\UniAccount::getEnable();
            foreach ($uniAccount as $u) {
                \YunShop::app()->uniacid = $u->uniacid;
                Setting::$uniqueAccountId = $u->uniacid;
                //余额与积分自定义移至语言设置
                $shopSet = Setting::get('shop.shop');
                $langSet = Setting::get('shop.lang', ['lang' => 'zh_cn']);
                $lang = $langSet['lang'] ? : 'zh_cn';
                $langSet[$lang]['member_center']['credit'] = $shopSet['credit'] ? : '余额';
                $langSet[$lang]['member_center']['credit1'] = $shopSet['credit1'] ? : '积分';
                Setting::set('shop.lang', $langSet);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lang_setting', function (Blueprint $table) {
            //
        });
    }
}
