<?php

namespace app\console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DomainProcess extends Command

{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'change:domain {dDomain} {oDomain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '域名替换';

    /**
     * 被替换域名
     *
     * @var string
     */
    private $oDomain = '';

    /**
     * 替换域名
     *
     * @var string
     */
    private $dDomain = '';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
	
        parent::__construct();
    }

    public function handle()
    {
        $this->oDomain = $this->argument('oDomain');
        $this->dDomain = $this->argument('dDomain');
        
        if (empty($this->oDomain) || empty($this->dDomain)) {
            \Log::debug('设置域名');
            exit;
        }

        $this->goods();
        $this->decorate();
        $this->orderGoods();
        $this->article();
        $this->uniacidApp();
    }

    private function goods()
    {
        $goods_field = [
            'thumb',
            'content',
			'thumb_url'
        ];

        \Log::debug('========= goods start=========');
		$search = $this->oDomain;
		$replace = $this->dDomain;

		foreach ($goods_field as $field) {
			$goods_sql = "update ims_yz_goods set $field=replace($field, '" . $search . "', '" . $replace . "')";
			DB::update($goods_sql);
		}

        \Log::debug('========= goods end=========');
    }

    private function decorate()
    {
        $deorate_field = [
            'datas',
            'page_info'
        ];

        \Log::debug('========= decorate start=========');

		$search = $this->oDomain;
		$replace = $this->dDomain;

		foreach ($deorate_field as $field) {
			$decorate_sql = "UPDATE ims_yz_decorate SET $field = REPLACE($field, '" . $search . "', '" . $replace . "')";
			DB::update($decorate_sql);
		}

        \Log::debug('========= decorate end=========');
    }

    private function orderGoods()
    {
        \Log::debug('========= orderGoods start=========');

        $search = $this->oDomain;
        $replace = $this->dDomain;

        $order_sql = "UPDATE ims_yz_order_goods SET thumb=REPLACE(thumb, '" . $search . "', '" . $replace . "')";
        DB::update($order_sql);

        \Log::debug('========= orderGoods end=========');
    }

    private function article()
    {
        \Log::debug('========= article start=========');

        $deorate_field = [
            'content',
            'thumb'
        ];

        $search = $this->oDomain;
        $replace = $this->dDomain;

        foreach ($deorate_field as $filed) {
           $article_sql = "UPDATE ims_yz_plugin_article SET $filed = REPLACE($filed, '" . $search .  "', '" . $replace . "')";
           DB::update($article_sql);
        }

        \Log::debug('========= article end=========');
    }

    private function uniacidApp()
    {
        \Log::debug('========= uniacidApp start=========');


        $search = $this->oDomain;
        $replace = $this->dDomain;

        $app_sql = "UPDATE ims_yz_uniacid_app SET img=REPLACE(img, '" . $search .  "', '" . $replace . "')";
        DB::update($app_sql);

        \Log::debug('========= uniacidApp end=========');
    }
}