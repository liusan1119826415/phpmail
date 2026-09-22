<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateImsYzOrderTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('yz_order')) {
            Schema::create('yz_order', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('uniacid')->unsigned()->default(0);
                $table->integer('uid')->default(0)->comment('用户id');
                $table->string('order_sn', 23)->default('')->comment('订单号');
                $table->integer('price')->default(0)->comment('订单金额');
                $table->integer('goods_price')->default(0)->comment('商品金额');
                $table->boolean('status')->default(0)->comment('订单状态 -1取消状态，0待付款，1为已付款，2为已发货，3为已完成');
                $table->integer('create_time')->default(0)->comment('创建时间');
                $table->boolean('is_deleted')->default(0)->comment('是否删除');
                $table->boolean('is_member_deleted')->default(0)->comment('1表示用户删除');
                $table->text('change_price_detail', 65535)->nullable()->comment('无用字段');
                $table->integer('finish_time')->default(0)->comment('交易完成时间');
                $table->integer('pay_time')->default(0)->comment('支付时间');
                $table->integer('send_time')->default(0)->comment('发货时间');
                $table->integer('cancel_time')->default(0)->comment('取消订单时间');
                $table->integer('created_at')->default(0)->comment('创建时间');
                $table->integer('updated_at')->default(0)->comment('更新时间');
                $table->integer('deleted_at')->default(0)->comment('删除时间');
                $table->integer('cancel_pay_time')->default(0)->comment('取消支付时间');
                $table->integer('cancel_send_time')->default(0)->comment('取消发货时间');
                $table->integer('dispatch_type_id')->default(0)->comment('发货方式id 0无需配送,1快递,2门店自提,3门店配送');
                $table->integer('pay_type_id')->default(0)->comment('支付方式id');
                $table->integer('is_plugin')->unsigned()->default(0)->comment('是否为供应商订单');
            });
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `".app('db')->getTablePrefix()
                ."yz_order` comment '商城订单表'");
        }
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('yz_order');
    }

}
