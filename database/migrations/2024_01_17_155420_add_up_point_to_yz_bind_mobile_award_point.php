<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddUpPointToYzBindMobileAwardPoint extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('yz_bind_mobile_award_point')) {
            Schema::table('yz_bind_mobile_award_point', function (Blueprint $table) {
                if (!Schema::hasColumn('yz_bind_mobile_award_point', 'up_point')) {
                    $table->decimal('up_point', 14)->nullable()->default(0.00)->comment('奖励上级积分');
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
