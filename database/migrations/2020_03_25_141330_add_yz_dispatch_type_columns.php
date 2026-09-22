<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddYzDispatchTypeColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('yz_dispatch_type')) {
            Schema::table('yz_dispatch_type', function (Blueprint $table) {
                if (!Schema::hasColumn('yz_dispatch_type', 'code')) {
                    $table->string('code')->nullable()->comment('插件标识');
                }
                if (!Schema::hasColumn('yz_dispatch_type', 'enable')) {
                    $table->tinyInteger('enable')->default(1)->comment('开启状态：0=关闭；1=开启');
                }
                if (!Schema::hasColumn('yz_dispatch_type', 'sort')) {
                    $table->tinyInteger('sort')->default(0)->comment('排序');
                }
                if (!Schema::hasColumn('yz_dispatch_type', 'created_at')) {
                    $table->integer('created_at')->nullable();
                }

                if (!Schema::hasColumn('yz_dispatch_type', 'updated_at')) {
                    $table->integer('updated_at')->nullable();
                }

            });
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
