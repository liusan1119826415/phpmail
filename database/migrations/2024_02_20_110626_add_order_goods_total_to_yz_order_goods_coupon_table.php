<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOrderGoodsTotalToYzOrderGoodsCouponTable extends Migration
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
                if (!Schema::hasColumn('yz_order_goods_coupon', 'order_goods_total')) {
                    $table->integer('order_goods_total')->default(0)->comment('商品购买数量');
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
