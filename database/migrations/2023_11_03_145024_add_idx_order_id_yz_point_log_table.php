<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdxOrderIdYzPointLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('yz_point_log')) {
            Schema::table('yz_point_log', function (Blueprint $table) {
                if (Schema::hasColumn('yz_point_log', 'order_id')) {
                    $sm = Schema::getConnection()->getDoctrineSchemaManager();
                    //获取该表的所有索引
                    $all_index = $sm->listTableIndexes(app('db')->getTablePrefix().'yz_point_log');
                    $flag = true;
                    foreach ($all_index as $index) {
                        if (in_array('order_id',$index->getColumns())) {
                            $flag = false;
                        }
                    }
                    //存在索引就不添加
                    $flag && $table->index('order_id','idx_order_id');
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
