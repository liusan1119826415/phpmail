<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateYzOrderGoodsCouponSendTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('yz_order_goods_coupon_send')) {
            Schema::create('yz_order_goods_coupon_send', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->integer('uniacid')->nullable(false);
                $table->integer('order_goods_coupon_id')->nullable()->comment('订单赠送优惠券id');
                $table->integer('member_coupon_id')->nullable()->comment('会员优惠券id');
                $table->integer('created_at')->nullable();
                $table->integer('updated_at')->nullable();
            });
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `" . app('db')->getTablePrefix() .
                "yz_order_goods_coupon_send` comment '订单--商品购买订单赠送优惠券发放记录'");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('yz_order_goods_coupon_send');
    }
}
