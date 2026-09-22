<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeYzOrderPayPayTypeIdTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        if (Schema::hasTable('yz_order_pay')) {
            Schema::table('yz_order_pay', function (Blueprint $table) {
                if (Schema::hasColumn('yz_order_pay', 'pay_type_id')) {
                    $table->integer('pay_type_id')->default(0)->change();
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
