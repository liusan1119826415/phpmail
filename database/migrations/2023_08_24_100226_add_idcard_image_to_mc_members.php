<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdcardImageToMcMembers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('mc_members')) {
            Schema::table('mc_members', function (Blueprint $table) {
                if (!Schema::hasColumn('mc_members', 'idcard_front')) {
                    $table->text('idcard_front')->nullable()->comment('身份证正面地址');
                }

                if (!Schema::hasColumn('mc_members', 'idcard_back')) {
                    $table->text('idcard_back')->nullable()->comment('身份证反面地址');
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
        Schema::table('mc_members', function (Blueprint $table) {
            //
        });
    }
}
