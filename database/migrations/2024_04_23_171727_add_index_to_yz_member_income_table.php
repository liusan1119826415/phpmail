<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndexToYzMemberIncomeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('yz_member_income')) {
            Schema::table('yz_member_income', function (Blueprint $table) {
                $idx = \Illuminate\Support\Facades\DB::select('show index from ' . app('db')->getTablePrefix() . 'yz_member_income where key_name="idx_incometable_type"');
                if (!$idx) {
                    \Illuminate\Support\Facades\DB::statement('alter table ' . app('db')->getTablePrefix() . 'yz_member_income add index `idx_incometable_type`(`incometable_type`)');
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
