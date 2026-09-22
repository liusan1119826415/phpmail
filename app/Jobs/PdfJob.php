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
use app\common\models\Goods;
use app\common\models\GoodsOption;
use app\common\services\upload\UploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use app\common\models\goods\ProductTemplate;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PdfJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $pdf_page;
    protected $pdf;
    public $goods_id;
    public $tries = 1;
    public $uniacid;
    public $timeout = 300;
    protected $goodsOptionIds;

    const BLOCK_PREFIX = "ABANGMI#";

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($goods_id,$uniacid)
    {
        $this->goods_id = $goods_id;
        $this->uniacid = $uniacid;
    }

    /**
     * @return bool|void
     */
    public function handle()
    {
        \YunShop::app()->uniacid = Setting::$uniqueAccountId = $this->uniacid;
        //根据页码获取pdf图片数据
        $goods = Goods::where('id',$this->goods_id)->first();
        $params = [
            "pdf_url"=>yz_tomedia($goods->e_catalog_pdf),
            "pdf_page"=>unserialize($goods->pdf_page),
            "goods_id"=>$this->goods_id
        ];
        $result = $this->processDwg("get_pdf_img",$params);
        if ($result['status'] == 1 && $result['data']['status_code'] == 200) {
            $pdf_page_img = $result['data']['output_png'];
           // $pdf_high_img = $result['data']['high_png'];
            $goods->pdf_page_img = serialize($pdf_page_img);
           // $goods->pdf_high_img = serialize($pdf_high_img);
            $goods->save();
        }
    }


    private function processDwg($api_method, $params)
    {
        $apiUrl = "http://127.0.0.1:8000/" . $api_method;
        $response = Http::post($apiUrl, $params);

        // 检查响应状态
        if ($response->successful()) {
            return ["status" => 1, "data" => $response->json()];
        } else {
            return ["status" => 0];
        }
    }


    private function uploadOss($save_path, $file_name)
    {

        $uploadedFile = new UploadedFile(
            $save_path,
            $file_name,
            mime_content_type($save_path),
            null,
            true // Mark as test file to avoid further validation
        );

        $uploadService = new UploadService();
        $upload_res = $uploadService->upload($uploadedFile, "file", "files");
        unlink($save_path);
        $relative_path = $upload_res['relative_path'];
        return $relative_path;
    }
}