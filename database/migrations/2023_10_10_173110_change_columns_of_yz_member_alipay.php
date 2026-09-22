<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeColumnsOfYzMemberAlipay extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('yz_member_alipay')) {
            Schema::table('yz_member_alipay', function (Blueprint $table) {
                if (Schema::hasColumn('yz_member_alipay', 'user_id')) {
                    $table->string('user_id')->comment('第三方用户id')->change();
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
