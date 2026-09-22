<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateYzPayRefundRecordTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        if (!Schema::hasTable('yz_pay_refund_record')) {
            Schema::create('yz_pay_refund_record', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->increments('id');
                $table->integer('uniacid')->default(0);
                $table->integer('pay_type_id')->nullable()->comment('支付类型ID');
                $table->string('pay_sn')->default('')->comment('支付号');
                $table->string('refund_sn')->default('')->comment('售后编号');
                $table->string('request_no')->default('')->comment('退款流水号');
                $table->decimal('amount',12,2)->nullable()->comment('发起退款金额');
                $table->text('desc')->nullable()->comment('退款提示');
                $table->tinyInteger('status')->default(0)->comment('状态：-1退款失败 0退款中 1退成功');
                $table->integer('created_at')->nullable();
                $table->integer('updated_at')->nullable();
            });
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `" . app('db')->getTablePrefix()
                . "yz_pay_refund_record` comment '支付方式退款记录'");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('yz_pay_refund_record');
    }
}
