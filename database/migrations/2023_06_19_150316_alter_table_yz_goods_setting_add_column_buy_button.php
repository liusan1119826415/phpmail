<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterTableYzGoodsSettingAddColumnBuyButton extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('yz_goods_setting')) {
            Schema::table('yz_goods_setting', function (Blueprint $table) {
                if (!Schema::hasColumn('yz_goods_setting', 'buy_button')) {
                    $table->string('buy_button')->default('buy|cart')->comment('商品详情页按钮开关，默认立即购买和加入购物车都显示');
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
