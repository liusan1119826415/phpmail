<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateYzMemberPluginsMinAppHomePage extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('yz_member_plugins_min_app_home_page', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('uniacid')->default(0)->index('indx_uniacid');
            $table->string('min_url')->default(0)->comment('插件主页小程序url');
            $table->integer('member_id')->default(0)->comment('会员id');
            $table->string('plugin_name')->default(0)->comment('插件名称');
            $table->integer('created_at')->nullable();
            $table->integer('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('yz_member_plugins_min_app_home_page');
    }
}
