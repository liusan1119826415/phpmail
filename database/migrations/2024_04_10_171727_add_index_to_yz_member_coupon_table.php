<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndexToYzMemberCouponTable extends Migration
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
                $idx = \Illuminate\Support\Facades\DB::select('show index from ' . app('db')->getTablePrefix() . 'yz_member_coupon where key_name="idx_uniacid"');
                if (!$idx) {
                    \Illuminate\Support\Facades\DB::statement('alter table ' . app('db')->getTablePrefix() . 'yz_member_coupon add index `idx_uniacid`(`uniacid`)');
                }
                $idx = \Illuminate\Support\Facades\DB::select('show index from ' . app('db')->getTablePrefix() . 'yz_member_coupon where key_name="idx_delete"');
                if (!$idx) {
                    \Illuminate\Support\Facades\DB::statement('alter table ' . app('db')->getTablePrefix() . 'yz_member_coupon add index `idx_delete`(`deleted_at`)');
                }
                $idx = \Illuminate\Support\Facades\DB::select('show index from ' . app('db')->getTablePrefix() . 'yz_member_coupon where key_name="idx_used"');
                if (!$idx) {
                    \Illuminate\Support\Facades\DB::statement('alter table ' . app('db')->getTablePrefix() . 'yz_member_coupon add index `idx_used`(`used`)');
                }
                $idx = \Illuminate\Support\Facades\DB::select('show index from ' . app('db')->getTablePrefix() . 'yz_member_coupon where key_name="idx_u_c"');
                if (!$idx) {
                    \Illuminate\Support\Facades\DB::statement('alter table ' . app('db')->getTablePrefix() . 'yz_member_coupon add index `idx_u_c`(`uniacid`, `coupon_id`)');
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
