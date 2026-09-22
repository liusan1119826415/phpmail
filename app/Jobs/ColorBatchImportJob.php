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


use app\common\exceptions\ShopException;
use app\common\facades\Setting;
use app\common\models\project\MaterialUv;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use Yunshop\Supplier\common\models\SupplierColorPlane;
use Yunshop\Supplier\common\models\SupplierColorCategory;
use Illuminate\Support\Facades\File;

class ColorBatchImportJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;


    public $tries = 1;


    public $timeout = 8000;


    public $uniacid;


    protected $zipFilePath;
    protected $taskId;

    protected $excelPath;

    protected $supplierId;

    protected $relativeDir;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($uniacid, $zipFilePath, $excelPath, $taskId, $supplierId,$relativeDir)
    {

        $this->uniacid = $uniacid;
        $this->taskId = $taskId;
        $this->zipFilePath = $zipFilePath;
        $this->excelPath = $excelPath;
        $this->supplierId = $supplierId;

        $this->relativeDir = $relativeDir;
    }

    /**
     * @return bool|void
     */
    public function handle()
    {
        \YunShop::app()->uniacid = Setting::$uniqueAccountId = $this->uniacid;

        // 初始化 Redis 连接
        $redis = \Illuminate\Support\Facades\Redis::connection();

        // 读取 Excel
        $data = Excel::toArray([], $this->excelPath)[0];

        // 图片目录
        $imageFolder = $this->zipFilePath."/".$this->relativeDir.'/images';

        $now = time();

        // 去掉表头
        $rows = array_slice($data, 1);
        $total = count($rows);
        $processed = 0;

        // 初始化 Redis 进度
        $redisKey = "import:progress:{$this->taskId}";
        $redis->hmset($redisKey, [
            'processed' => 1,
            'status' => 0,
            'total' => $total,
            'updated_at' => $now
        ]);

        foreach ($rows as $index => $row) {
            [$cateName, $subCateName, $imageName, $metalTypeName] = $row;

            // 一级分类
            $cate = SupplierColorCategory::firstOrCreate(
                [
                    'uniacid' => $this->uniacid,
                    'supplier_id' => $this->supplierId,
                    'name' => trim($cateName),
                    'parent_id' => 0
                ],
                ['created_at' => $now, 'updated_at' => $now]
            );

            // 处理二级分类（只有当subCateName不为空时才处理）
            $finalCateId = $cate->id; // 默认使用一级分类ID
            $cateIds = (string)$cate->id; // 初始化分类ID路径

            if (!empty(trim($subCateName))) {
                $subCate = SupplierColorCategory::firstOrCreate(
                    [
                        'uniacid' => $this->uniacid,
                        'supplier_id' => $this->supplierId,
                        'name' => trim($subCateName),
                        'parent_id' => $cate->id
                    ],
                    ['created_at' => $now, 'updated_at' => $now]
                );

                $finalCateId = $subCate->id; // 使用二级分类ID
                $cateIds .= ',' . $subCate->id; // 更新分类ID路径
            }

            // 图片处理
            $thumbPath = null;
            if ($imageName) {
                $localPath = $imageFolder . '/' . $imageName;
                if (file_exists($localPath)) {
                    $upload_data = uploadOssV2($localPath, uniqid() . '.' . pathinfo($localPath, PATHINFO_EXTENSION),0);
                    $thumbPath = $upload_data['relative_path'];
                } else {
                    \Log::error("图片不存在: {$localPath}");
                }
            }

            // 金属度映射
            $MaterialUv = MaterialUv::where('name', $metalTypeName)->first();

            // 入库
            SupplierColorPlane::create([
                'uniacid' => $this->uniacid,
                'supplier_id' => $this->supplierId,
                'name' => trim(explode(".",$imageName)[0]),
                'thumb' => $thumbPath,
                'uv_id' => $MaterialUv->id ?: 0,
                'cate_id' => $finalCateId,
                'cate_ids' => $cateIds,
                'created_at' => $now,
            ]);

            // 更新进度
            $processed++;
            $percent = min(100, intval($processed / $total * 100));

            // 更新 Redis 进度
            $updateInterval = max(1, intval($total / 100));
            if ($processed % $updateInterval === 0 || $processed === $total) {
                $redis->hmset($redisKey, [
                    'processed' => $percent,
                    'updated_at' => time()
                ]);
            }
        }

        // 标记任务完成
        $redis->hmset($redisKey, [
            'processed' => 100,
            'status' => 1,
            'updated_at' => time()
        ]);

        // 设置过期时间（1天后自动删除）
        $redis->expire($redisKey, 86400);

        // 清理临时文件
        File::deleteDirectory($this->zipFilePath);
    }


}