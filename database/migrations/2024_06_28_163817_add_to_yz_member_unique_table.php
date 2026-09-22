<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddToYzMemberUniqueTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('yz_member_unique')) {
            Schema::table('yz_member_unique', function (Blueprint $table) {
                if (!Schema::hasColumn('yz_member_unique', 'scope')) {
                    $table->integer('scope')->default(1)->comment('开放平台类型1微信2抖音');
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
