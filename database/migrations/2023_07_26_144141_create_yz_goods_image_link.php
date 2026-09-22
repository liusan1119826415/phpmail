<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateYzGoodsImageLink extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('yz_goods_image_link')) {
            Schema::create('yz_goods_image_link', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('uniacid')->default(0);
                $table->integer('goods_id')->comment('商品id');
                $table->integer('status')->comment('是否启用 1是 0否');
                $table->string('image_web_link')->default('')->comment('商品详情H5跳转链接');
                $table->string('image_mini_link')->default('')->comment('商品详情小程序跳转路由');
                $table->string('button_name')->default('')->comment('按钮名称');
                $table->integer('created_at')->nullable();
                $table->integer('updated_at')->nullable();
            });
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `" . app('db')->getTablePrefix() . "yz_goods_image_link` comment'商品--详情图跳转链接'");//表注释
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('yz_goods_image_link');
    }
}
