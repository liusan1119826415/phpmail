<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAddressToYzReturnExpressTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('yz_return_express')) {
            Schema::table('yz_return_express', function (Blueprint $table) {
                if (!Schema::hasColumn('yz_return_express', 'address')) {
                    $table->string('address')->default('')->comment( '物流发货地址数据冗余记录');
                }

                if (!Schema::hasColumn('yz_return_express', 'contacts_info')) {
                    $table->text('contacts_info')->nullable()->comment( '物流发货地址数据冗余记录');
                }

                if (!Schema::hasColumn('yz_return_express', 'pack_goods')) {
                    $table->text('pack_goods')->nullable()->comment( '发货商品数据冗余');
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
        Schema::table('yz_return_express', function (Blueprint $table) {
            //
        });
    }
}
