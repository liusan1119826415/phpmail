<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsDefaultGoodReputationShowToYzCommentConfigTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('yz_comment_config')) {
            Schema::table('yz_comment_config', function (Blueprint $table) {
                if (!Schema::hasColumn('yz_comment_config', 'is_default_good_reputation_show')) {
                    $table->boolean('is_default_good_reputation_show')->default(0)->comment('显示默认好评评论:1-显示，0-关闭');
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
        Schema::table('yz_comment_config', function (Blueprint $table) {
            if (Schema::hasColumn('yz_comment_config', 'is_default_good_reputation_show')) {
                $table->dropColumn(['is_default_good_reputation_show']);
            }
        });
    }
}
