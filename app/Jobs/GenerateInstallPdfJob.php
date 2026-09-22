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

use app\backend\modules\goods\services\ExtractImageVector;
use app\common\facades\Setting;
use app\common\models\Goods;
use app\common\models\GoodsOption;
use app\common\services\upload\UploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use app\common\models\project\ProjectPdf;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class GenerateInstallPdfJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;


    public $tries = 1;


    public $timeout = 8000;


    public $data;

    public $key;

    public $uniacid;

    public $option_id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data,$option_id,$uniacid,$key)
    {
         $this->data = $data;
         $this->option_id = $option_id;
         $this->uniacid = $uniacid;

         $this->key = $key;
    }

    /**
     * @return bool|void
     */
    public function handle()
    {
        \YunShop::app()->uniacid = Setting::$uniqueAccountId = $this->uniacid;
        $goodsOption = GoodsOption::uniacid()->where('id', $this->option_id)->first();

        if (!$goodsOption) {
            \Log::error("商品规格未找到: " . $this->option_id);
            return false;
        }


        if (md5($goodsOption->{$this->key}) == md5(serialize($this->data))) {
            \Log::error("==={$this->key}数据相等不需要修改===");
            return true;
        }
        if(empty($this->data['data'])){
            return true;
        }
        // ✅ 提取并处理 high_url（确保 $this->data 已定义）
        $highUrls = array_map(function($item) {
            return yz_tomedia($item['high_url']);
        }, $this->data['data']);

        $output = storage_path("app/public/pdfimg/{$this->key}{$this->option_id}.pdf");
        $params = [
            'image_urls' => $highUrls,
            'output_pdf' => $output,
        ];

        // ✅ 调用生成 PDF
        $response = processDwg("generatePdf", $params);
        if ($response['data']['status_code'] != 200) {
            \Log::error("生成 PDF 失败: " . json_encode($response));
            return false;
        }

        // ✅ 上传到 OSS
        $res = uploadOss($output, "{$this->key}{$this->option_id}.pdf", 0);
        if (!$res || !isset($res['absolute_path'])) {
            \Log::error("上传 OSS 失败");
            return false;
        }

        // ✅ 更新商品数据
        $propertyName = $this->key . "_url";  // 例如：high_url
        $goodsOption->{$this->key} = serialize($this->data);
        $goodsOption->{$propertyName} = $res['absolute_path']; // ✅ 直接赋值，不需要 $$
        $goodsOption->save();

        return true;
    }

}