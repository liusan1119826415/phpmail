<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsShopToYzCommentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('yz_comment')) {
            Schema::table('yz_comment', function (Blueprint $table) {
                if (!Schema::hasColumn('yz_comment', 'is_shop')) {
                    $table->boolean('is_shop')->default(0)->comment('1是商家回复');
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
        Schema::table('yz_comment', function (Blueprint $table) {
            //
        });
    }
}
