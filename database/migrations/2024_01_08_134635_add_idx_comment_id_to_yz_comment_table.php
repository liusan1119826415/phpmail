<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdxCommentIdToYzCommentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('yz_comment', function (Blueprint $table) {
            if (Schema::hasColumn('yz_comment', 'comment_id')) {
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                //获取该表的所有索引
                $all_index = $sm->listTableIndexes(app('db')->getTablePrefix().'yz_comment');
                $flag = true;
                foreach ($all_index as $index) {
                    if (in_array('comment_id',$index->getColumns())) {
                        $flag = false;
                    }
                }
                //存在索引就不添加
                $flag && $table->index('comment_id','idx_comment_id');
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
        Schema::table('yz_comment', function (Blueprint $table) {
            //
        });
    }
}
