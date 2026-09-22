<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateImsYzMemberIncomeChangeAmountLog extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        if (!Schema::hasTable('yz_member_income_change_amount_log')) {
            Schema::create('yz_member_income_change_amount_log', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('uniacid')->default(0)->index('uid_idx');
                $table->integer('income_id')->nullable()->comment('提现id');
                $table->decimal('before', 14)->default(0)->comment('修改前金额');
                $table->decimal('after', 14)->default(0)->comment('修改后金额');
                $table->integer('operator_id')->nullable()->comment('操作员id');
                $table->string('ip')->nullable()->comment('ip地址');
                $table->integer('created_at')->nullable();
                $table->integer('updated_at')->nullable();
            });
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `" . app('db')->getTablePrefix()
                . "yz_member_income_change_amount_log` comment '提现金额修改记录表'");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
