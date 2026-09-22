<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexDisplayOrderToYzGoodsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('yz_goods', function (Blueprint $table) {
            if (Schema::hasColumn('yz_goods', 'display_order')) {
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                //获取该表的所有索引
                $all_index = $sm->listTableIndexes(app('db')->getTablePrefix().'yz_goods');
                $flag = true;
                foreach ($all_index as $index) {
                    if (in_array('display_order',$index->getColumns())) {
                        $flag = false;
                    }
                }
                //存在索引就不添加
                $flag && $table->index('display_order','idx_display_order');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('yz_goods', function (Blueprint $table) {
            //
        });
    }
}
