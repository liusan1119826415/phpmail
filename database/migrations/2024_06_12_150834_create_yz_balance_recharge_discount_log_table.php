<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateYzBalanceRechargeDiscountLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('yz_balance_recharge_discount_log')) {
            Schema::create('yz_balance_recharge_discount_log',
                function (Blueprint $table) {
                    $table->engine = 'InnoDB';
                    $table->increments('id');
                    $table->integer('uniacid')->default(0);
                    $table->integer('member_id')->default(0)->comment('会员ID');
                    $table->integer('recharge_id')->default(0)->comment('充值记录ID');
                    $table->integer('discount_id')->default(0)->comment('优惠项ID');
                    $table->string('code')->default('')->comment('优惠标识');
                    $table->string('name')->default('')->comment('优惠名称');
                    $table->string('desc')->default('')->comment('优惠描述，备注方便排查');
                    $table->decimal('amount',10,2)->default(0)->comment('优惠金额');
                    $table->integer('created_at')->nullable();
                    $table->integer('updated_at')->nullable();
                    $table->integer('deleted_at')->nullable();
                });
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `".app('db')->getTablePrefix()."yz_balance_recharge_discount_log` comment'余额--余额充值优惠记录'");//表注释
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('yz_balance_recharge_discount_log');
    }
}
