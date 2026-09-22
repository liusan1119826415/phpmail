<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateYzImportProgressTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('yz_import_progress')) {
            Schema::create('yz_import_progress', function (Blueprint $table) {
                $table->id();
                $table->string('task_id')->unique(); // 每个任务唯一
                $table->integer('processed')->default(0); // 已完成百分比
                $table->tinyInteger('status')->default(0)->comment("0 进度中 1 完成"); // processing, completed
                $table->integer('created_at')->nullable();
                $table->integer('updated_at')->nullable();


            });
        }
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE `" . app('db')->getTablePrefix() . "yz_import_progress` comment '导入进度'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('yz_import_progress');
    }
}
