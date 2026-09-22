<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOrderGoodsIdsToYzMemberRelation extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('yz_member_relation')) {
            Schema::table('yz_member_relation', function (Blueprint $table) {
                if (!Schema::hasColumn('yz_member_relation', 'order_goods_ids')) {
                    $table->text('order_goods_ids')->nullable()->comment('锁定上级下单/支付指定商品ID');
                }
                if (!Schema::hasColumn('yz_member_relation', 'order_goods_check')) {
                    $table->tinyInteger('order_goods_check')->default(0)->comment('锁定上级下单/支付指定商品开关');
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

    }
}
