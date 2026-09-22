<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterTableYzCouponAddColumnWaitDays extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        if (Schema::hasTable('yz_coupon')) {
            Schema::table('yz_coupon', function (Blueprint $table) {
                if (!Schema::hasColumn('yz_coupon', 'wait_days')) {
                    $table->decimal('wait_days',10,2)->default(0)->comment('x天后开始使用')->after('time_end');
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
        //
    }
}
