<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIntervalToYzOrderGoodsCouponTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('yz_order_goods_coupon')) {
            Schema::table('yz_order_goods_coupon', function (Blueprint $table) {
                if (!Schema::hasColumn('yz_order_goods_coupon', 'interval')) {
                    $table->integer('interval')->default(0)->comment('间隔X时间发放');
                }
                if (!Schema::hasColumn('yz_order_goods_coupon', 'next_send_time')) {
                    $table->integer('next_send_time')->default(0)->comment('下一次发放时间');
                }
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
        Schema::table('yz_order_goods_coupon', function (Blueprint $table) {
            //
        });
    }
}
