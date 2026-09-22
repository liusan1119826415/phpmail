<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateImsYzPayWithdrawOrderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('yz_pay_withdraw_order')) {
            Schema::create('yz_pay_withdraw_order', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('uniacid')->comment('平台ID');
                $table->integer('member_id')->comment('会员ID');
                $table->string('int_order_no', 32)->comment('提现单号');
                $table->string('out_order_no', 32)->comment('提现号');
                $table->string('trade_no', 255)->nullable()->comment('交易号');
                $table->decimal('price', 14, 2)->comment('提现金额');
                $table->string('type', 255)->comment('提现方式');
                $table->integer('status')->comment('状态：0-发起提现  1-提现预下单  2-提现成功');
                $table->integer('created_at')->default(0);
                $table->integer('updated_at')->default(0);
                $table->integer('deleted_at')->nullable();
            });
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `" . app('db')->getTablePrefix() .
                "yz_pay_withdraw_order` comment '提现单记录'");
        }
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('yz_pay_withdraw_order');
    }
}
