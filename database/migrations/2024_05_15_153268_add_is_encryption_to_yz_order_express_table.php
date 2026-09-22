<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIsEncryptionToYzOrderExpressTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('yz_order_express')) {
            Schema::table('yz_order_express', function (Blueprint $table) {
                if (!Schema::hasColumn('yz_order_express', 'is_encryption')) {
                    $table->integer('is_encryption')->default(0)->comment('是否勾选信息加密,仅顺丰使用');
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
