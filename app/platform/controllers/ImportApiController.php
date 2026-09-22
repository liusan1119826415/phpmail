<?php

namespace app\platform\controllers;

use app\common\services\goods\CreateGoodsService;
use app\common\services\upload\UploadService;
use app\common\models\Goods;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 批量导入商品 API（供 Python 客户端调用）
 * 路由前缀: /admin/importApi
 */
class ImportApiController extends BaseController
{
    protected $isPublic = true;

    /**
     * 健康检查
     * GET /admin/importApi/ping
     */
    public function ping()
    {
        return $this->successJson([], "ok");
    }

    /**
     * 文件上传（代理到 UploadService）
     * POST /admin/importApi/upload
     * 参数: file(上传文件), is_high(0/1), thumb_type, upload_type
     */
    public function upload(Request $request)
    {
        try {
            $file = $request->file('file');
            if (!$file || !$file->isValid()) {
                return $this->errorJson([], "未收到有效文件");
            }

            $isHigh = intval($request->input('is_high', 0));
            $thumbType = $request->input('thumb_type', 'detail');
            $uploadType = $request->input('upload_type', 'image');

            Log::info("ImportApi upload", [
                'filename' => $file->getClientOriginalName(),
                'is_high' => $isHigh,
                'thumb_type' => $thumbType,
                'upload_type' => $uploadType,
            ]);

            $uploadService = new UploadService($isHigh, $thumbType);
            $result = $uploadService->uploadForImport($file, $uploadType);

            Log::info("ImportApi upload result", ['result' => $result]);

            // successJson 参数顺序: 第1个→msg, 第2个→data
            return $this->successJson("上传成功", $result);

        } catch (\Exception $e) {
            Log::error("ImportApi upload error: " . $e->getMessage());
            return $this->errorJson([], "上传失败: " . $e->getMessage());
        }
    }

    /**
     * 导入商品（接收 Python 组装好的数据，调用 CreateGoodsService）
     * POST /admin/importApi/importGoods
     */
    public function importGoods(Request $request)
    {
        // 调试: 确认请求到达控制器
        Log::info("ImportApi importGoods 请求到达");
        
        try {
            Log::info("ImportApi step 1: 读取 raw input");
            // 读取原始请求体并去除 UTF-8 BOM
            $rawInput = file_get_contents('php://input');
            Log::info("ImportApi step 2: rawInput length", ['len' => strlen($rawInput)]);
            
            $rawInput = preg_replace('/^\x{EF}\xBB\xBF/', '', $rawInput);  // 去除 BOM
            
            // 解析 JSON
            $data = json_decode($rawInput, true);
            Log::info("ImportApi step 3: json_decode", ['error' => json_last_error(), 'data_keys' => is_array($data) ? array_keys($data) : 'not array']);
            
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                $data = $request->all();
                Log::info("ImportApi step 3b: fallback to request->all", ['keys' => array_keys($data)]);
            }

            if (empty($data['title'])) {
                Log::info("ImportApi step 4: title is empty");
                return $this->errorJson([], "商品标题不能为空");
            }
            
            Log::info("ImportApi step 5: title ok", ['title' => $data['title']]);

            // 调试: 记录图片/视频数据
            Log::info("ImportApi 图片/视频数据", [
                'thumb_url_count' => count($data['thumb_url'] ?? []),
                'real_image_count' => count($data['real_image'] ?? []),
                'wiring_diagram_count' => count($data['wiring_diagram'] ?? []),
                'goods_video' => $data['goods_video'] ?? '',
                'atlas' => isset($data['atlas']) ? 'present' : 'missing',
                'e_catalog_pdf' => $data['e_catalog_pdf'] ?? '',
            ]);

            // 检查商品是否已存在
            Log::info("ImportApi step 6: checking exists");
            $exists = Goods::where('title', $data['title'])
                ->where('supp_id', $data['supplier_id'] ?? 0)
                ->exists();
            Log::info("ImportApi step 7: exists check done", ['exists' => $exists]);

            if ($exists) {
                Log::info("ImportApi 商品已存在，跳过", ['title' => $data['title']]);
                // successJson 参数顺序: 第1个→msg, 第2个→data
                return $this->successJson("商品已导入", ['exists' => true, 'title' => $data['title']]);
            }

            // 处理序列化字段（与 CreateGoodsService::importGoods 保持一致）
            Log::info("ImportApi step 8: creating CreateGoodsService");
            $createGoodsService = new CreateGoodsService($request, 1);
            Log::info("ImportApi step 9: calling importGoods");
            $result = $createGoodsService->importGoods($data);
            Log::info("ImportApi step 10: importGoods returned");

            // 调试: 记录导入结果
            Log::info("ImportApi 导入结果", [
                'title' => $data['title'],
                'result_type' => gettype($result),
                'result' => $result,
            ]);

            $response = $this->successJson($result, "导入成功");
            
            // 调试: 记录返回给客户端的响应
            Log::info("ImportApi response", ['response' => $response->getData(true)]);
            
            return $response;

        } catch (\Throwable $e) {
            Log::error("ImportApi catch 块执行", ['exception' => get_class($e), 'message' => $e->getMessage()]);
            
            $errMsg = $e->getMessage();
            // 防止 getMessage() 返回数组导致 JSON 序列化异常
            if (!is_string($errMsg)) {
                $errMsg = json_encode($errMsg, JSON_UNESCAPED_UNICODE);
            }
            Log::error("ImportApi importGoods error: " . $errMsg . " | trace: " . $e->getTraceAsString());
            // 同时记录完整异常信息到日志
            Log::error("ImportApi exception detail", ['exception' => $e]);
            return $this->errorJson(['trace' => $e->getTraceAsString()], "导入失败: " . $errMsg);
        }
    }

    /**
     * 检查商品是否已存在
     * GET /admin/importApi/checkGoods?title=xxx&supplier_id=35
     */
    public function checkGoods(Request $request)
    {
        $title = $request->input('title', '');
        $supplierId = $request->input('supplier_id', 0);

        $exists = false;
        if ($title && $supplierId) {
            $exists = Goods::where('title', $title)
                ->where('supp_id', $supplierId)
                ->exists();
        }

        return $this->successJson(['exists' => $exists]);
    }
}
