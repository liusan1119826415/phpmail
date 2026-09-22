<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateYzNotificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('yz_notifications')) {
            Schema::create('yz_notifications', function (Blueprint $table) {
                $table->id();
                $table->integer('member_id')->index()->comment('接收用户ID');
                $table->string('title', 100)->comment('通知标题');
                $table->text('content')->comment('通知内容');
                $table->enum('notice_type', ['system', 'order', 'audit', 'transaction'])->comment('通知类型');
                $table->string('sub_type', 50)->nullable()->comment('子类型');
                $table->integer('related_id')->nullable()->index()->comment('关联业务ID');
                $table->boolean('is_read')->default(false)->comment('是否已读');
                $table->timestamp('read_at')->nullable()->comment('阅读时间');
                $table->json('extra_data')->nullable()->comment('扩展数据');
                $table->integer('created_at')->nullable();
                $table->integer('updated_at')->nullable();
                $table->index(['member_id', 'notice_type', 'is_read']);
                $table->index('created_at');

            });
        }
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE `" . app('db')->getTablePrefix() . "yz_notifications` comment '消息通知'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('yz_notifications');
    }
}
