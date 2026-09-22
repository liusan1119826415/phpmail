<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateImsYzBindMailAwardPointTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
        if (!Schema::hasTable('yz_bind_mail_award_point')) {
            Schema::create('yz_bind_mail_award_point', function (Blueprint $table) {
                $table->integer('id', true)->comment('主键ID');
                $table->integer('uniacid')->comment('平台ID');
                $table->integer('member_id')->comment('会员ID');
                $table->decimal('point', 14)->nullable()->default(0.00)->comment('奖励积分');
                $table->decimal('up_point', 14)->nullable()->default(0.00)->comment('奖励上级积分');
                $table->integer('created_at')->comment('创建时间');
                $table->integer('updated_at')->comment('修改时间');
            });
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `" . app('db')->getTablePrefix()
                . "yz_bind_mail_award_point` comment '绑定邮箱积分奖励记录表'");
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
