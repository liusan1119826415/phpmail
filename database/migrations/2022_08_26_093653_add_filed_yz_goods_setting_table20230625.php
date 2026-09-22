<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFiledYzGoodsSettingTable20230625 extends Migration
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
                if (!Schema::hasColumn('yz_goods_setting', 'scribing_show')) {
                    $table->tinyInteger('scribing_show')->default(0)->comment('原价划线显示：0显示划线1不显示划线');
                }
                if (!Schema::hasColumn('yz_goods_setting', 'is_show_min_share')) {
                    $table->tinyInteger('is_show_min_share')->default(0)->comment('商品详情页-显示分享链接');
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
