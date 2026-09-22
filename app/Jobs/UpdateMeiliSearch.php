<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2023/4/19
 * Time: 17:38
 */

namespace app\Jobs;

use app\common\facades\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use app\backend\modules\goods\services\GoodsMeiliSearchService;

class UpdateMeiliSearch implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;


    protected $pdf;

    public $tries = 1;



    public $timeout = 300;


    public $uniacid;


    public $goods_id;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($uniacid,$goods_id)
    {

        $this->uniacid = $uniacid;  
        $this->goods_id = $goods_id;

    }

    /**
     * @return bool|void
     */
    public function handle()
    {
         \Log::debug('更新商品索引开始');
         \YunShop::app()->uniacid = Setting::$uniqueAccountId = $this->uniacid;

         $goodsMeiliSearch = new GoodsMeiliSearchService();

         $goodsMeiliSearch->reindexByGoodsId($this->goods_id);

         \Log::debug('更新商品索引结束');


    }




   
}