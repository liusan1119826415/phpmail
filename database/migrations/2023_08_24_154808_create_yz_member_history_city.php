<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateYzMemberHistoryCity extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('yz_member_history_city')) {
            Schema::create('yz_member_history_city', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->integer('uniacid')->nullable(false);
                $table->integer('member_id')->nullable(false)->comment('用户id');
                $table->integer('city_id')->nullable(false)->comment('城市id');
                $table->string('city_name')->nullable(false)->comment('城市名称');
                $table->integer('created_at')->nullable();
                $table->integer('updated_at')->nullable();
                $table->integer('deleted_at')->nullable();
            });
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `" . app('db')->getTablePrefix() .
                "yz_member_history_city` comment '会员最近访问城市记录'");
        }


    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('yz_member_history_city');
    }
}
