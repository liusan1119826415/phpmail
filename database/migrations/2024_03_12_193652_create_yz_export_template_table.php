<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateYzExportTemplateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('yz_export_template')) {
            Schema::create('yz_export_template', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('uniacid')->default(0);
                $table->string('export_type')->index('idx_export_type')->nullable()->comment('模版类型');
                $table->string('name')->nullable()->comment('名称');
                $table->text('columns')->nullable()->comment('导出字段');
                $table->integer('created_at')->nullable();
                $table->integer('updated_at')->nullable();
                $table->integer('deleted_at')->nullable();
            });
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `" . app('db')->getTablePrefix()
                . "yz_export_template` comment '导出模版表'");
        }
        if (!Schema::hasTable('yz_export_record')) {
            Schema::create('yz_export_record', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('uniacid')->default(0)->index('idx_uniacid');
                $table->integer('template_id')->nullable()->comment('模版id');
                $table->string('export_type')->index('idx_export_type')->nullable()->comment('模版类型');
                $table->text('columns')->nullable()->comment('导出字段');
                $table->text('request')->nullable()->comment('搜索字段');
                $table->text('action')->default('')->comment('指定action');
                $table->tinyInteger('status')->default(0)->comment('状态');
                $table->integer('total')->default(0)->comment('导出数量');
                $table->integer('user_id')->index('idx_user_id')->default(0)->comment('导出管理员id');
                $table->string('source_name')->default('')->comment('源文件名称');
                $table->string('download_name')->default('')->comment('下载名称');
                $table->integer('created_at')->nullable();
                $table->integer('updated_at')->nullable();
                $table->integer('deleted_at')->nullable();
            });
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `" . app('db')->getTablePrefix()
                . "yz_export_record` comment '导出记录表'");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('yz_export_template');
    }
}
