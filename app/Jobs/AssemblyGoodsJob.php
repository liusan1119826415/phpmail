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
use Illuminate\Support\Facades\File;
class AssemblyGoodsJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;


    public $goods_id;
    public $tries = 1;
    public $uniacid;
    public $timeout = 300;
    public $params;

    public $base64thumb;
    const BLOCK_PREFIX = "ABANGMI#";


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($goods_id,$uniacid,$params,$base64thumb)
    {
        $this->goods_id = $goods_id;
        $this->uniacid = $uniacid;
        $this->params = $params;
        $this->base64thumb = $base64thumb;

    }

    /**
     * @return bool|void
     */
    public function handle()
    {
        \YunShop::app()->uniacid = Setting::$uniqueAccountId = $this->uniacid;
        $goodsOption = GoodsOption::where('goods_id',$this->goods_id)->first();
        $output = storage_path("app/public/tmp");
        $processedData = $this->processDwg("assemble", $this->params);
        if ($processedData['status'] == 1 && $processedData['data']['status_code'] == 200) {
            $output_dwg_url = $this->uploadOss($this->params['output_dwg'], basename($this->params['output_dwg']));

            $api_method = "process-dwg";
            $block_name = self::BLOCK_PREFIX . $this->goods_id . "#" . $goodsOption->id;
            $params = [
                'url' => yz_tomedia($output_dwg_url),
                'new_block_name' => $block_name,
                "output" => $output,
            ];
            $result = $this->processDwg($api_method, $params);
            $save_path = $output . "/" . $block_name . "." . "dwg";
            $file_name = $block_name . "." . "dwg";
            $relative_path = $this->uploadOss($save_path, $file_name);
            \Log::debug("==========================上传结果========",$relative_path);
            $goodsOption->cad_plan_model =$relative_path;
            $goodsOption->save();

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



    protected function uploadOssThumb($input)
    {
        $uniqid = uniqid();
        // If $input is base64, decode and save as an image file
        $save_path = storage_path("app/public/tmp") . "/" . $uniqid . ".png";
        $this->decodeAndSaveBase64($input, $save_path);


        $uploadedFile = new UploadedFile(
            $save_path,
            $uniqid . ".png",
            "image/png",
            null,
            false // Mark as test file to avoid further validation
        );

        $uploadService = new UploadService();

        $upload_res = $uploadService->upload($uploadedFile, "image");


        unlink($save_path);
        $image_url = $upload_res['relative_path'];
        return $image_url;
    }


    protected function decodeAndSaveBase64($base64Str, $savePath)
    {
        // 检查是否包含 'data:image/...' 前缀
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Str, $matches)) {
            // 如果包含，去掉前缀部分并解码

            $base64Str = substr($base64Str, strpos($base64Str, ',') + 1);
        }
        // 解码 Base64 数据
        $fileContents = base64_decode($base64Str);


        // 保存到指定路径
        File::put($savePath, $fileContents);

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