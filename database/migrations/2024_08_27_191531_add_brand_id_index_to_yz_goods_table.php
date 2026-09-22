<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBrandIdIndexToYzGoodsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('yz_goods', function (Blueprint $table) {
            if (Schema::hasColumn('yz_goods', 'brand_id')) {
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                //获取该表的所有索引
                $all_index = $sm->listTableIndexes(app('db')->getTablePrefix() . 'yz_goods');
                $flag = true;
                foreach ($all_index as $index) {
                    if (in_array('brand_id', $index->getColumns())) {
                        $flag = false;
                    }
                }
                if ($flag) {
                    $table->index('brand_id');
                }
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
        
    }
}
