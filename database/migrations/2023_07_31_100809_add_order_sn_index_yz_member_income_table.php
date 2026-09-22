<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOrderSnIndexYzMemberIncomeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('yz_member_income', function (Blueprint $table) {
            if (Schema::hasColumn('yz_member_income', 'order_sn')) {
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                //获取该表的所有索引
                $all_index = $sm->listTableIndexes(app('db')->getTablePrefix().'yz_member_income');
                $flag = true;
                foreach ($all_index as $index) {
                    if (in_array('order_sn',$index->getColumns())) {
                        $flag = false;
                    }
                }
                //存在索引就不添加
                $flag && $table->index('order_sn','idx_order_sn');
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
        //
    }
}
