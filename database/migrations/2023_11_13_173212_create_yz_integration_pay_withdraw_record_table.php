<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateYzIntegrationPayWithdrawRecordTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('yz_integration_pay_withdraw_record')) {
            Schema::create('yz_integration_pay_withdraw_record',
                function (Blueprint $table) {
                    $table->engine = 'InnoDB';
                    $table->increments('id');
                    $table->integer('uniacid')->default(0);
                    $table->integer('plugin_id')->default(0);
                    $table->string('merchant_no')->comment('提现商户号');
                    $table->string('withdraw_sn')->comment('提现流水号');
                    $table->string('wallet_id')->comment('钱包ID');
                    $table->decimal('amount',14,2)->default(0.00)->comment('提现金额');
                    $table->string('fee_rate',10)->default(0)->comment('手续费比例或固定金额');
                    $table->string('draw_mode',100)->default('')->comment('提款模式');
                    $table->string('settle_type',100)->default('')->comment('结算模式');
                    $table->string('draw_status',100)->default('')->comment('提款状态');
                    $table->text('details')->nullable()->comment('提现详情');
                    $table->integer('complete_at')->nullable();
                    $table->integer('created_at')->nullable();
                    $table->integer('updated_at')->nullable();
                    $table->integer('deleted_at')->nullable();
                });
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `".app('db')->getTablePrefix()."yz_integration_pay_withdraw_record` comment'聚合支付--提现记录'");//表注释
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('yz_integration_pay_withdraw_record');
    }
}
