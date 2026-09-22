<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddActualPayToYzBalanceRechargeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        if (Schema::hasTable('yz_balance_recharge')) {
            Schema::table('yz_balance_recharge', function (Blueprint $table) {
                if (!Schema::hasColumn('yz_balance_recharge', 'actual_pay')) {
                    $table->decimal('actual_pay',14,2)->nullable()->comment( '充值实付金额');
                }

                if (!Schema::hasColumn('yz_balance_recharge', 'is_invoice')) {
                    $table->integer('is_invoice')->default(0)->comment( '是否已开发票：0否 大于1：是');
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
//        Schema::table('yz_balance_recharge', function (Blueprint $table) {
//
//        });
    }
}
