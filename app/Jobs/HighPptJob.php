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

class HighPptJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;


    protected $pdf;

    public $tries = 1;



    public $timeout = 8000;


    public $params;
    public $template_ppt;

    public $host;

    public $id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    /*public function __construct($id,$params,$template_ppt,$host)
    {
        $this->id = $id;
        $this->params = $params;
        $this->template_ppt = $template_ppt;
        $this->host = $host;

    }*/
    public function __construct()
    {

    }

    /**
     * @return bool|void
     */
    public function handle()
    {
        //根据页码获取pdf图片数据
        /*$ProjectPdf= ProjectPdf::where('id',$this->id)->first();
        $request_data['template_ppt'] = $this->template_ppt;
        $request_data['params'] = $this->params;
        $request_data['is_high'] = 1;
        $result = $this->processDwg("replace_zip",$request_data);
        if ($result['status'] == 1 && $result['data']['status_code'] == 200) {
            $ProjectPdf->thumb_url = "https://".$this->host."/ppt/".$result['data']['output_pdf'];
            $ProjectPdf->save();
        }*/
        $vector = new ExtractImageVector();
        $vector->handle();
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