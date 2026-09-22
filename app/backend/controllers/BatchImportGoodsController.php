<?php

namespace app\backend\controllers;

use app\common\components\BaseController;
use app\common\facades\Setting;
use app\Jobs\BatchImportGoodsJob;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;

class BatchImportGoodsController extends BaseController
{
    protected $isPublic = true;

    /**
     * 提交批量导入任务
     * POST /admin/importGoods/submit
     *
     * 参数:
     * - supplier_id: 供应商ID (必填)
     * - product_type: 商品类型 (可选)
     * - excel_file: Excel文件 (必填, 通过 file 上传)
     * - image_dir: 图片相对目录 (可选)
     */
    public function submit()
    {
        $supplierId = request()->input('supplier_id');
        $productType = request()->input('product_type', '');
        $imageDir = request()->input('image_dir', '');

        if (!$supplierId) {
            return $this->successJson('供应商ID不能为空', []);
        }

        // 处理 Excel 文件上传
        if (!request()->hasFile('excel_file')) {
            return response()->json([
                'result' => 0,
                'msg' => '请上传Excel文件',
                'data' => []
            ]);
        }

        $file = request()->file('excel_file');
        $ext = strtolower($file->getClientOriginalExtension());

        if (!in_array($ext, ['xls', 'xlsx', 'xlsm'])) {
            return response()->json([
                'result' => 0,
                'msg' => '仅支持 xls/xlsx/xlsm 格式',
                'data' => []
            ]);
        }

        // 保存上传文件到临时目录
        $tmpDir = storage_path('app/import_goods/' . uniqid());
        if (!File::exists($tmpDir)) {
            File::makeDirectory($tmpDir, 0755, true);
        }
        $file->move($tmpDir, 'import.' . $ext);
        $excelFile = $tmpDir . '/import.' . $ext;

        try {
            $uniacid = 1;
            \YunShop::app()->uniacid = Setting::$uniqueAccountId = $uniacid;

            // 解析 Excel
            $data = Excel::toArray([], $excelFile);

            // 生成任务ID
            $taskId = 'import_goods_' . uniqid();

            // 派发到队列（异步执行，不会超时）
            $job = new BatchImportGoodsJob([
                'uniacid'    => $uniacid,
                'taskId'     => $taskId,
                'supplierId' => $supplierId,
                'tmpPath'    => $tmpDir,
                'data'       => $data,
                'relativeDir'=> $imageDir,
                'productType'=> $productType,
            ]);
            dispatch($job);

            // 初始化 Redis 进度
            $progressData = [
                'task_id'      => $taskId,
                'progress'     => 0,
                'total'        => 0,
                'success'      => 0,
                'failed'       => 0,
                'failed_goods' => [],
                'update_time'  => date('Y-m-d H:i:s'),
                'status'       => 0, // 0=进行中, 1=完成
            ];
            Redis::setex($taskId, 3600 * 24, json_encode($progressData));

            return response()->json([
                'result' => 1,
                'msg'    => '导入任务已提交',
                'data'   => [
                    'task_id' => $taskId,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'result' => 0,
                'msg'    => '提交失败: ' . $e->getMessage(),
                'data'   => []
            ]);
        }
    }

    /**
     * 查询导入任务进度
     * GET /admin/importGoods/progress?task_id=xxx
     */
    public function progress()
    {
        $taskId = request()->input('task_id');

        if (!$taskId) {
            return response()->json([
                'result' => 0,
                'msg'    => 'task_id 不能为空',
                'data'   => []
            ]);
        }

        try {
            $progressJson = Redis::get($taskId);

            if (!$progressJson) {
                return response()->json([
                    'result' => 0,
                    'msg'    => '任务不存在或已过期',
                    'data'   => []
                ]);
            }

            $progressData = json_decode($progressJson, true);

            return response()->json([
                'result' => 1,
                'msg'    => 'success',
                'data'   => $progressData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'result' => 0,
                'msg'    => '查询失败: ' . $e->getMessage(),
                'data'   => []
            ]);
        }
    }
}
