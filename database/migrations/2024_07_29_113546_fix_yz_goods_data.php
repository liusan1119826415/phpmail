<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixYzGoodsData extends Migration
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
                if (Schema::hasColumn('yz_goods', 'hide_goods_pic')&&Schema::hasColumn('yz_goods', 'background_pic')&&Schema::hasColumn('yz_goods', 'advert_pic')) {
                    \Illuminate\Support\Facades\DB::table('yz_goods')->where('plugin_id','<>',0)->update(['background_pic'=>null,'advert_pic'=>null,'hide_goods_pic'=>0]);
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
