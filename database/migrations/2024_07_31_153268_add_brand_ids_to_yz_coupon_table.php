<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBrandIdsToYzCouponTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('yz_coupon')) {
            Schema::table('yz_coupon', function (Blueprint $table) {
                if (!Schema::hasColumn('yz_coupon', 'brand_ids')) {
                    $table->text('brand_ids')->nullable()->comment('品牌ID');
                }
                if (!Schema::hasColumn('yz_coupon', 'is_all_brand')) {
                    $table->tinyInteger('is_all_brand')->default(0)->comment('是否全部品牌可用 1是 0不是');
                }
                if (!Schema::hasColumn('yz_coupon', 'brand_id')) {
                    $table->integer('brand_id')->default(0)->comment('单个品牌ID');
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
