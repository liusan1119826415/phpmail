<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateImsYzCategoryBalance extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        if (!Schema::hasTable('yz_category_balance')) {
            Schema::create('yz_category_balance', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('uniacid')->default(0);
                $table->text('category_ids')->nullable()->comment('分类id');
                $table->tinyInteger('balance_deduct')->default(0)->comment('余额抵扣开关');
                $table->string('min_balance_deduct',10)->nullable()->comment('余额最低抵扣');
                $table->string('max_balance_deduct',10)->nullable()->comment('余额最高抵扣');
                $table->integer('created_at')->nullable();
                $table->integer('updated_at')->nullable();
            });
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `" . app('db')->getTablePrefix()
                . "yz_category_balance` comment '商品分类批量设置余额表'");
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
