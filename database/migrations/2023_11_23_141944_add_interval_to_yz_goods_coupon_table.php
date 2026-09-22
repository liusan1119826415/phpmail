<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIntervalToYzGoodsCouponTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('yz_goods_coupon')) {
            Schema::table('yz_goods_coupon', function (Blueprint $table) {
                if (!Schema::hasColumn('yz_goods_coupon', 'interval')) {
                    $table->integer('interval')->default(0)->comment('间隔发放时间：send_type=3时单位为天');
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
        Schema::table('yz_goods_coupon', function (Blueprint $table) {
            //
        });
    }
}
