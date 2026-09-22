<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateImsYzDispatchTypeTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
        if (!Schema::hasTable('yz_dispatch_type')) {
            Schema::create('yz_dispatch_type', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 50)->default('')->comment('配送方式名称');
                $table->integer('plugin')->comment('插件id');
            });
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE " . app('db')->getTablePrefix() . "yz_dispatch_type comment '配送类型表'");//表注释

        }
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::dropIfExists('yz_dispatch_type');
	}

}
