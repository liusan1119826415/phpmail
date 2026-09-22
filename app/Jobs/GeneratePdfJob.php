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

class GeneratePdfJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;


    public $tries = 1;


    public $timeout = 8000;


    public $data;

    public $key;

    public $uniacid;

    public $goods_id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data,$goods_id,$uniacid,$key)
    {
         $this->data = $data;
         $this->goods_id = $goods_id;
         $this->uniacid = $uniacid;

         $this->key = $key;
    }

    /**
     * @return bool|void
     */
    public function handle()
    {
        \YunShop::app()->uniacid = Setting::$uniqueAccountId = $this->uniacid;
        $goods = Goods::uniacid()->where('id', $this->goods_id)->first();

        if (!$goods) {
            \Log::error("商品未找到: " . $this->goods_id);
            return false;
        }

       /* if (!property_exists($goods, $this->key) || !property_exists($this, $this->key)) {
            \Log::error("无效的属性键: " . $this->key);
            return false;
        }*/

        if (md5($goods->{$this->key}) == md5(serialize($this->data))) {
            \Log::error("==={$this->key}数据相等不需要修改===");
            return true;
        }

        // ✅ 提取并处理 high_url（确保 $this->data 已定义）
        $highUrls = array_map(function($item) {
            return yz_tomedia($item['high_url']);
        }, $this->data['data']);

        $output = storage_path("app/public/pdfimg/{$this->key}.pdf");
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
        $res = uploadOss($output, "{$this->key}.pdf", 0);
        if (!$res || !isset($res['absolute_path'])) {
            \Log::error("上传 OSS 失败");
            return false;
        }

        // ✅ 更新商品数据
        $propertyName = $this->key . "_url";  // 例如：high_url
        $goods->{$this->key} = serialize($this->data);
        $goods->{$propertyName} = $res['absolute_path']; // ✅ 直接赋值，不需要 $$
        $goods->save();

        return true;
    }

}