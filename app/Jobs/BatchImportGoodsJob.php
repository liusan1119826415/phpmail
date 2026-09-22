<?php


namespace app\Jobs;


use app\common\facades\Setting;
use app\common\models\Goods;

use app\common\services\Session;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use Yunshop\Supplier\supplier\services\ImportGoodsService;
use Illuminate\Support\Facades\File;
class BatchImportGoodsJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;


    public $tries = 1;



    public $timeout = 864000;


    public $data;


    public $uniacid;

    public $relativeDir;

    public $productType;

    public $taskId;

    public $supplierId;

    public $tmpPath;



    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($params)
    {
         $this->uniacid = $params['uniacid'];
         $this->taskId = $params['taskId'];
         $this->supplierId = $params['supplierId'];
         $this->tmpPath = $params['tmpPath'];
         $this->data = $params['data'];
         $this->relativeDir = $params['relativeDir'];

         $this->productType = $params['productType'];

    }

    /**
     * @return bool|void
     */
    public function handle()
    {

        \YunShop::app()->uniacid = Setting::$uniqueAccountId = $this->uniacid;


        $ImportGoodsService = new ImportGoodsService();

        $file_path = $this->tmpPath."/".$this->relativeDir;
        \Log::error("=====daoru==file_path==",$file_path);
        $ImportGoodsService->init($this->data,$file_path,$this->uniacid,$this->taskId,$this->supplierId,$this->productType);



    }


    private function findExcelFile($dir)
    {
        $files = File::allFiles($dir); // 递归获取所有文件

        foreach ($files as $file) {
            if (preg_match('/\.(xls|xlsx)$/i', $file->getFilename())) {
                $fullPath = $file->getRealPath();
                $relativePath = str_replace($dir . '/', '', dirname($fullPath));

                return [
                    'fullPath' => $fullPath,          // 完整文件路径
                    'relativeDir' => $relativePath    // 相对于根目录的父目录路径
                ];
            }
        }
        return null;
    }



}