<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterYzGoodsAddColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('yz_goods')) {

            Schema::table('yz_goods', function (Blueprint $table) {
                if (!Schema::hasColumn('yz_goods', 'hide_goods_pic')) {
                    $table->tinyInteger('hide_goods_pic')->default(0)->comment('开启后，商品详情页不显示商品图片和其他图片。只有当首图视频有上传时开启才有效');
                }
                if (!Schema::hasColumn('yz_goods', 'background_pic')) {
                    $table->string('background_pic')->nullable()->comment('商品详情页主图背景图');
                }
                if (!Schema::hasColumn('yz_goods', 'advert_pic')) {
                    $table->string('advert_pic')->nullable()->comment('广告图');
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
