<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateYzGoodsExportTemplateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('yz_goods_export_template')) {
            Schema::create('yz_goods_export_template', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->integer('uniacid')->nullable()->comment('公众号id');
                $table->string('name')->nullable()->comment('模板名称');
                $table->text('checked_column')->comment('导出字段 使用,连接');
                $table->integer('plugin_id')->default(0)->comment('插件ID');
                $table->integer('relation_id')->default(0)->comment('关系ID，用于比如需要关联供应商ID之类的');
                $table->integer('created_at')->nullable();
                $table->integer('updated_at')->nullable();
            });
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `" . app('db')->getTablePrefix() .
                "yz_goods_export_template` comment '商品导出模板'");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('yz_goods_export_template');
    }
}
