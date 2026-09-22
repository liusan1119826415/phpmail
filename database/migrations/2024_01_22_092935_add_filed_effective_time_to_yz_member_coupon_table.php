<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFiledEffectiveTimeToYzMemberCouponTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('yz_member_coupon')) {
            Schema::table('yz_member_coupon', function (Blueprint $table) {
                if (!Schema::hasColumn('yz_member_coupon', 'effective_time')) {
                    $table->integer('effective_time')->default(0)->comment('生效时间');
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
        Schema::table('yz_coupon', function (Blueprint $table) {
            //
        });
    }
}
