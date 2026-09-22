<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBalanceTypeToYzGoodsSale extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('yz_goods_sale')) {
            Schema::table('yz_goods_sale', function (Blueprint $table) {
                if (!Schema::hasColumn('yz_goods_sale', 'balance_deduct_type')) {
                    $table->integer('balance_deduct_type')->default(0)->comment('余额抵扣类型，0固定值，1支付金额*比例');
                }
            });
        }
        if (Schema::hasTable('yz_category_balance')) {
            Schema::table('yz_category_balance', function (Blueprint $table) {
                if (!Schema::hasColumn('yz_category_balance', 'balance_deduct_type')) {
                    $table->integer('balance_deduct_type')->default(0)->comment('余额抵扣类型，0固定值，1支付金额*比例');
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
    }
}
