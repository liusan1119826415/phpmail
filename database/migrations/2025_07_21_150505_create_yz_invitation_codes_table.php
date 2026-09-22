<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateYzInvitationCodesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('yz_invitation_codes')) {
            Schema::create('yz_invitation_codes', function (Blueprint $table) {
                $table->id();
                $table->string('code', 32)->unique();
                $table->integer('expires_at');
                $table->integer('max_uses')->default(1);
                $table->tinyInteger('status')->default(1);
                $table->integer('used_count')->default(0);
                $table->integer('created_at')->nullable();
                $table->integer('updated_at')->nullable();


            });
        }
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE `" . app('db')->getTablePrefix() . "yz_invitation_codes` comment '邀请码注册'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('yz_invitation_codes');
    }
}
