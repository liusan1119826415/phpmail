<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateYzMemberStateGrid extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('yz_member_state_grid')) {
            Schema::create('yz_member_state_grid', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->integer('uniacid')->nullable(false);
                $table->integer('member_id')->nullable(false)->comment('会员id');
                $table->string('avatar')->nullable()->comment('头像');
                $table->string('channel')->nullable()->comment('注册渠道');
                $table->string('channel_type')->nullable()->comment('渠道类型');
                $table->string('email')->nullable()->comment('邮箱');
                $table->tinyInteger('enabled')->nullable()->comment('注册渠道');
                $table->text('extra')->nullable()->comment('不知道是什么');
                $table->integer('out_id')->nullable()->comment('主键');
                $table->tinyInteger('is_online')->nullable()->comment('是否在线');
                $table->string('last_login_at')->nullable()->comment('最后一次登陆时间');
                $table->tinyInteger('locked')->nullable()->comment('是否冻结');
                $table->string('mobile')->nullable()->comment('手机号');
                $table->string('nickname')->nullable()->comment('昵称');
                $table->string('origin')->nullable()->comment('用户源');
                $table->tinyInteger('password_exist')->nullable()->comment('密码是否存在');
                $table->integer('pk')->nullable()->comment('用户标识pk');
                $table->string('prefix')->nullable()->comment('手机号前缀');
                $table->string('pwd_expired')->nullable()->comment('过期时间');
                $table->string('source')->nullable()->comment('用户类型');
                $table->string('source_type')->nullable()->comment('来源类型');
                $table->string('tag')->nullable()->comment('标签');
                $table->string('tenant_id')->nullable()->comment('租户id');
                $table->string('created_at_')->nullable()->comment('注册时间');
                $table->string('updated_at_')->nullable()->comment('更新时间');
                $table->text('user_detail')->nullable()->comment('详情');
                $table->string('username')->nullable()->comment('用户名');
                $table->string('encryption_pwd')->nullable()->comment('加密密码');
                $table->integer('created_at')->nullable();
                $table->integer('updated_at')->nullable();
                $table->integer('deleted_at')->nullable();
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
        Schema::dropIfExists('yz_member_state_grid');
    }
}
