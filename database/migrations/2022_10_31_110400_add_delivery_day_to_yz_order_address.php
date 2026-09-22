<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDeliveryDayToYzOrderAddress extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
		if (Schema::hasTable('yz_order_address')) {
			Schema::table('yz_order_address', function (Blueprint $table) {
				if (!Schema::hasColumn('yz_order_address', 'delivery_day')) {
					$table->string("delivery_day")->nullable()->comment("配送日期");
				}
			});

			Schema::table('yz_order_address', function (Blueprint $table) {
				if (!Schema::hasColumn('yz_order_address', 'delivery_time')) {
					$table->string("delivery_time")->nullable()->comment("配送时间");
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
