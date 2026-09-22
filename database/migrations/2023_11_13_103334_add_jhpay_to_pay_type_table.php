<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddJhpayToPayTypeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!\app\common\models\PayType::find(130)) {

            \app\common\models\PayType::insert([
                'id' => 130,
                'name' => '微信-聚合',
                'plugin_id' => 0,
                'code' => 'wxIntegrationPay',
                'type' => 2,
                'unit' => '分',
                'group_id' => 1,
                'setting_key' => 'payment.integration_pay',
                'created_at' => time(),
                'updated_at' => time(),
            ]);
        }

        if(!\app\common\models\PayType::find(127)) {

            \app\common\models\PayType::insert([
                'id' => 127,
                'name' => '支付宝-聚合',
                'plugin_id' => 0,
                'code' => 'zfbIntegrationPay',
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
    }
}
