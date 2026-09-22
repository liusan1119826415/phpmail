<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFiledCouponImgToYzCouponTable extends Migration
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
                if (!Schema::hasColumn('yz_coupon', 'coupon_img')) {
                    $table->string('coupon_img')->default('')->comment('优惠券图片');
                }
                if (!Schema::hasColumn('yz_coupon', 'scope_content_diy')) {
                    $table->text('scope_content_diy')->default("")->comment('适用范围内容自定义');
                }
                if (!Schema::hasColumn('yz_coupon', 'usage_rules')) {
                    $table->text('usage_rules')->default("")->comment('使用规则');
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
        Schema::table('yz_coupon', function (Blueprint $table) {
            //
        });
    }
}
