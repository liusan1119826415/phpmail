<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateImsYzGoodsReturnAddressTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('yz_goods_return_address')) {
            Schema::create('yz_goods_return_address',function (Blueprint $table) {
                $table->increments('id');
                $table->integer('uniacid');
                $table->string('address_name' ,32)->nullable('退货地址名称');
                $table->string('contact', 20)->comment('联系人');
                $table->string('mobile', 11)->comment('手机');
                $table->string('telephone', 11)->comment('电话');
                $table->integer('province_id')->comment('省份ID');
                $table->string('province_name', 32)->comment('省份名称');
                $table->integer('city_id')->comment('城市ID');
                $table->string('city_name', 32)->comment('城市名称');
                $table->integer('district_id')->comment('区域ID');
                $table->string('district_name', 32)->comment('区域名称');
                $table->integer('street_id')->nullable()->comment('街道ID');
                $table->string('street_name', 32)->nullable()->comment('街道名称');
                $table->string('address', 512)->comment('详细地址');
                $table->integer('plugins_id')->default(0)->comment('插件ID');
                $table->integer('store_id')->default(0)->comment('区域代理ID');
                $table->integer('supplier_id')->default(0)->comment('供应商ID');
                $table->boolean('is_default')->default(0)->comment('是否默认:1-默认，0-非默认');
                $table->integer('created_at')->default(0);
                $table->integer('updated_at')->default(0);
            });
        }

        \Illuminate\Support\Facades\DB::statement("ALTER TABLE `" . app('db')->getTablePrefix() . "yz_goods_return_address` comment '商品-配送模板详情'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('ims_yz_goods_return_address')) {

            Schema::drop('ims_yz_goods_return_address');
        }
    }
}
