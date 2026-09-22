<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateYzUsedInvitationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('yz_used_invitations')) {
            Schema::create('yz_used_invitations', function (Blueprint $table) {
                $table->id();
                $table->integer('code_id');
                $table->integer('member_id');
                $table->integer('created_at')->nullable();
                $table->integer('updated_at')->nullable();


            });
        }
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE `" . app('db')->getTablePrefix() . "yz_used_invitations` comment '邀请码使用表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('yz_used_invitations');
    }
}
