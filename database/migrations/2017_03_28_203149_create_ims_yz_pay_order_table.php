<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateImsYzPayOrderTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
        if (!Schema::hasTable('yz_pay_order')) {
            Schema::create('yz_pay_order', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('uniacid')->index('idx_uniacid')->comment('平台ID');
                $table->integer('member_id')->index('idx_member_id')->comment('会员ID');
                $table->string('int_order_no', 32)->nullable()->comment('支付单号');
                $table->string('out_order_no', 32)->default('0')->index('idx_order_no')->comment('订单支付单号');
                $table->string('trade_no', 255)->nullable()->comment('交易号');
                $table->tinyInteger('status')->default(0)->comment('状态：0未付款1待付款2完成');
                $table->decimal('price', 14, 2)->comment('支付金额');
                $table->tinyInteger('type')->comment('支付类型：1订单支付，2余额充值');
                $table->string('third_type', 255)->comment('支付方式');
                $table->integer('created_at')->default(0);
                $table->integer('updated_at')->default(0);
                $table->integer('deleted_at')->nullable();
            });
        }
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::dropIfExists('yz_pay_order');
	}

}
