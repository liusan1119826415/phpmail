<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateYzMemberTiktokTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('yz_member_tiktok')) {
            Schema::create('yz_member_tiktok', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('uniacid');
                $table->integer('member_id')->default(0)->comment('登录会员ID');
                $table->string('openid', 50)->default('')->comment('抖音openid');
                $table->string('nickname', 20)->default('')->comment('昵称');
                $table->string('avatar')->default('')->comment('头像');
//                $table->boolean('gender')->default(0)->comment('性别0未知1男2女');
                $table->integer('created_at')->default(0);
                $table->integer('updated_at')->default(0);
                $table->integer('deleted_at')->nullable();
            });
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `" . app('db')->getTablePrefix()
                . "yz_member_tiktok` comment '抖音会员粉丝表'");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('yz_member_tiktok');
    }
}
