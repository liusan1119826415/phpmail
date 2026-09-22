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
use app\common\models\project\ProjectPdf;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploadPptJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;


    protected $pdf;

    public $tries = 1;



    public $timeout = 300;


    public $params;
    public $template_ppt;

    public $host;

    public $id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($id)
    {
        $this->id = $id;


    }

    /**
     * @return bool|void
     */
    public function handle()
    {
        //根据页码获取pdf图片数据
        $ProjectPdf= ProjectPdf::where('id',$this->id)->first();
        $save_path = "/www/wwwroot/bangmi_dwg/output_pdf/".basename($ProjectPdf->thumb_url);
        $file_name = basename($ProjectPdf->thumb_url);
        $relative_path = $this->uploadOss($save_path,$file_name);
        $ProjectPdf->ppt_formal_url =$relative_path;
        $ProjectPdf->save();


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