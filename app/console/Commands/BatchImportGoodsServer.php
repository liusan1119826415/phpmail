<?php

namespace app\Console\Commands;

use app\common\facades\Setting;
use app\common\services\Session;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\File;
use app\common\services\goods\ImportGoodsService;

class BatchImportGoodsServer extends Command
{
// 修改签名，添加必要的参数
    protected $signature = 'import:goods
                            {action : 执行动作}
                            {--supplier_id= : 供应商ID}
                            {--product_type= : 商品类型}
                            {--dir= : Excel文件目录路径}
                            {--d : 调试模式}';

    protected $description = 'Start the import goods server';

    public function __construct()
    {
        parent::__construct();
    }

    private function findExcelFile($dir)
    {
        $files = File::allFiles($dir); // 递归获取所有文件

        foreach ($files as $file) {
            if (preg_match('/\.(xls|xlsx|xlsm)$/i', $file->getFilename())) {
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

    public function handle()
    {
// 获取传入的参数
        $uniacid = 1;
        $supplierId = $this->option('supplier_id');
       // $productType = $this->option('product_type');
        $tmpPath = $this->option('dir');

        \YunShop::app()->uniacid = Setting::$uniqueAccountId = $uniacid;

// 验证必要参数
        if (!$supplierId) {
            $this->error('供应商ID不能为空');
            return 1;
        }

//        if (!$productType) {
//            $this->error('商品类型不能为空');
//            return 1;
//        }

        if (!$tmpPath) {
            $this->error('目录路径不能为空');
            return 1;
        }

        // 检查目录是否存在
        if (!File::exists($tmpPath)) {
            $this->error('指定的目录不存在: ' . $tmpPath);
            return 1;
        }
        

        $excelData = $this->findExcelFile($tmpPath);

        if (!$excelData) {
            $this->error("目录中未找到Excel文件: " . $tmpPath);
            return 1;
        }

        $excelFile = $excelData['fullPath'];

        $relativeDir = $excelData['relativeDir'];

        try {
            $data = Excel::toArray([], $excelFile);

            $taskId = 'import_goods' . uniqid();
            $importGoodsService = new ImportGoodsService();

            $this->info("开始导入任务: " . $taskId);
            $this->info("供应商ID: " . $supplierId);
          //  $this->info("商品类型: " . $productType);

            $res = $importGoodsService->init($data, $relativeDir, $uniacid, $taskId, $supplierId);

            if ($res) {
                $this->info("导入任务完成: " . $taskId);
            } else {
                $this->error("导入任务失败: " . $taskId);
                return 1;
            }

        } catch (\Exception $e) {
            $this->error("导入过程中发生错误: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}