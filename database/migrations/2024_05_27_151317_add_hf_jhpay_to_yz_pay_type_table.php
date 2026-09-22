<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHfJhpayToYzPayTypeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        if(!\app\common\models\PayType::find(141)) {

            \app\common\models\PayType::insert([
                'id' => 141,
                'name' => '快捷支付-聚合支付',
                'plugin_id' => 0,
                'code' => 'hfkjIntegrationPay',
                'type' => 2,
                'unit' => '分',
                'group_id' => 1,
                'setting_key' => 'payment.integration_pay',
                'created_at' => time(),
                'updated_at' => time(),
            ]);
        }

        if(!\app\common\models\PayType::find(142)) {

            \app\common\models\PayType::insert([
                'id' => 142,
                'name' => '小程序终端-聚合支付',
                'plugin_id' => 0,
                'code' => 'hfMiniIntegrationPay',
                'type' => 2,
                'unit' => '分',
                'group_id' => 2,
                'setting_key' => 'payment.integration_pay',
                'created_at' => time(),
                'updated_at' => time(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
//        Schema::table('yz_pay_type', function (Blueprint $table) {
//            //
//        });
    }
}
