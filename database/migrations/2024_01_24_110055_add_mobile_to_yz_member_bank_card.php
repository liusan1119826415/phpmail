<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMobileToYzMemberBankCard extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('yz_member_bank_card')) {
            Schema::table('yz_member_bank_card', function (Blueprint $table) {
                $table->string('idcard')->nullable()->comment('身份证号');
                $table->string('mobile')->nullable()->comment('银行卡预留手机号');
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
