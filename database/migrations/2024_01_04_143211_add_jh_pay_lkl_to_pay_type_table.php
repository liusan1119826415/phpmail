<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddJhPayLklToPayTypeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!\app\common\models\PayType::find(134)) {

            \app\common\models\PayType::insert([
                'id' => 134,
                'name' => '拉卡拉-收银台',
                'plugin_id' => 0,
                'code' => 'lklIntegrationPay',
                'type' => 2,
                'unit' => '分',
                'group_id' => 0,
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
