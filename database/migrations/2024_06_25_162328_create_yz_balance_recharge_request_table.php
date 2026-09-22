<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateYzBalanceRechargeRequestTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('yz_balance_recharge_request')) {
            Schema::create('yz_balance_recharge_request',
                function (Blueprint $table) {
                    $table->engine = 'InnoDB';
                    $table->increments('id');
                    $table->integer('uniacid')->default(0);
                    $table->integer('recharge_id')->default(0)->comment('充值记录ID');
                    $table->text('request')->nullable()->comment('请求参数');
                    $table->string('request_ip')->default('')->comment('IP');
                    $table->integer('created_at')->nullable();
                    $table->integer('updated_at')->nullable();
                    $table->integer('deleted_at')->nullable();
                });
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `".app('db')->getTablePrefix()."yz_balance_recharge_request` comment'余额--余额充值请求参数'");//表注释
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('yz_balance_recharge_request');
    }
}
