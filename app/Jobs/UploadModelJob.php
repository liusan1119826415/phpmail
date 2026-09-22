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

class UploadModelJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;


    protected $pdf;

    public $tries = 1;



    public $timeout = 300;


    public $params;
    public $template_ppt;

    public $host;

    public $option_id;

    public $fullPath;

    public $fileName;

    public $uniacid;

    public $type;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($option_id,$uniacid,$fullPath,$fileName=null,$type)
    {
        $this->option_id = $option_id;
        $this->fullPath = $fullPath;
        $this->fileName = $fileName;
        $this->uniacid = $uniacid;
        $this->type = $type;
    }

    /**
     * @return bool|void
     */
    public function handle()
    {

        $waitTime = 0;
        while (!file_exists($this->fullPath) && $waitTime < 10) {
            sleep(1); // 每秒检查一次
            $waitTime++;
        }
        \Log::debug("============模型文件上传====");
        if (!file_exists($this->fullPath)) {
            \Log::error("超时未找到文件，无法上传: " . $this->fullPath);
            return;
        }

        \YunShop::app()->uniacid = Setting::$uniqueAccountId = $this->uniacid;
        $goodsOption = GoodsOption::where('id',$this->option_id)->first();


        if($this->type == 1){
            $d3ModelUrl_weld = $this->uploadOssTwo($this->fullPath, $this->fileName);
            $goodsOption->d3ModelUrl_weld = $d3ModelUrl_weld;
            // unlink($this->fullPath);
        }elseif($this->type == 2){
            $d3ModelUrl_weld = $this->uploadOssTwo($this->fullPath, $this->fileName);
            $goodsOption->d3ModelUrl_ori = $d3ModelUrl_weld;
            // unlink($this->fullPath);
        }

        $goodsOption->save();




    }




    private function uploadOssTwo($save_path, $file_name)
    {

        $uploadedFile = new UploadedFile(
            $save_path,
            $file_name,
            mime_content_type($save_path),
            null,
            true // Mark as test file to avoid further validation
        );

        $uploadService = new UploadService();
        $upload_res = $uploadService->uploadTwo($uploadedFile, "file", "files");

        $relative_path = $upload_res['relative_path'];
        return $relative_path;
    }


    private function uploadOss($file)
    {

        $uploadService = new UploadService();
        $upload_res = $uploadService->uploadTwo($file, "file", "files");

        $relative_path = $upload_res['relative_path'];
        return $relative_path;
    }
}