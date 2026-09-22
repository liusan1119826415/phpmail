<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWeightToYzOrderGoods extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('yz_order_goods')) {
            Schema::table('yz_order_goods', function (Blueprint $table) {
                if (!Schema::hasColumn('yz_order_goods', 'weight')) {
                    $table->decimal('weight', 10, 2)->default(0)->comment('称重重量');
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
