<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddThirdStatusIntoPoint extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('yz_point_log',function (Blueprint $table) {
            $table->string('thirdStatus')->after('remark')->nullable()->default(1)->comment('前后端均未发现其作用，但是有进行字段的更新，不可删除');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('yz_point_log', function (Blueprint $table) {
            $table->dropColumn('thirdStatus');
        });
    }
}
