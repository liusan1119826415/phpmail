<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeYzOrderPayTypeIdTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        if (Schema::hasTable('yz_order')) {
            Schema::table('yz_order', function (Blueprint $table) {
                if (Schema::hasColumn('yz_order', 'pay_type_id')) {
                    $table->integer('pay_type_id')->default(0)->change();
                }
                if (Schema::hasColumn('yz_order', 'dispatch_type_id')) {
                    $table->integer('dispatch_type_id')->default(0)->change();
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
       
    }
}
