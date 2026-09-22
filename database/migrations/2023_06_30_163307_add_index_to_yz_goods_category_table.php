<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexToYzGoodsCategoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('yz_goods_category', function (Blueprint $table) {
            if (Schema::hasColumn('yz_goods_category', 'category_id')) {
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                //获取该表的所有索引
                $all_index = $sm->listTableIndexes(app('db')->getTablePrefix().'yz_goods_category');
                $flag = true;
                foreach ($all_index as $index) {
                    if (in_array('category_id',$index->getColumns())) {
                        $flag = false;
                    }
                }
                //存在索引就不添加
                $flag && $table->index('category_id','idx_category_id');
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
        Schema::table('yz_goods_category', function (Blueprint $table) {
            //
        });
    }
}
