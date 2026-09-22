<?php


namespace app\frontend\modules\goods\services;


use app\common\services\upload\UploadService;
use app\frontend\models\GoodsOption;
use app\common\models\goods\ProductTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class BlockService
{


    public function getCacheBlock($goods_id, $jsonData)
    {
        $cacheKey = 'blocks:' . md5($jsonData);
        $lockKey = $cacheKey . ':lock';

        // 检查 Redis 缓存
        if (Redis::exists($cacheKey)) {
            $cachedData = Redis::get($cacheKey);
            return ['status' => 1, 'data' => json_decode($cachedData, true)];
        }

        // 尝试获取分布式锁
        $lockAcquired = Redis::set($lockKey, 'locked', 'NX', 'EX', 3); // 锁定10秒
        if ($lockAcquired) {
            try {
                $uniqid = uniqid();
                $ProductTemplate = ProductTemplate::where('goods_id', $goods_id)->first();
                if (!$ProductTemplate) {
                    return ['status' => 0, 'data' => "Invalid GoodsID"];
                }
                // 如果缓存不存在，执行业务逻辑
                $template_dwg = yz_tomedia($ProductTemplate->template_dwg);
               /* foreach ($jsonArrData as $key => $item) {
                    $GoodsOption = GoodsOption::where('id', $item['id'])->first();
                    $jsonArrData[$key]['cad_url'] = yz_tomedia($GoodsOption->cad_plan_model);
                }*/

                $params = [
                    "jsondata" => $jsonData,
                    "template" => $template_dwg,
                    "output" => storage_path("app/public/tmp") . "/" . $uniqid . ".png",
                    "output_dwg" => storage_path("app/public/tmp") . "/" . $uniqid . ".dwg"
                ];
                $processedData = $this->processDwg("assemble", $params);


                if ($processedData['status'] == 1 && $processedData['data']['status_code'] == 200) {
                    $img_url = $this->uploadOss($params['output'], $uniqid . ".png");
                    $output_dwg_url = $this->uploadOss($params['output_dwg'], $uniqid . ".dwg");
                    $response['img_url'] = $img_url;
                    $response['output_dwg_url'] = $output_dwg_url;
                    // 将结果存入 Redis 缓存，设置缓存时间（例如：1小时）
                    $baseCacheTime = 24 * 60 * 60; // 基础缓存时间：一天
                    $randomFactor = rand(80, 120) / 100; // 生成 0.8 到 1.2 的随机数
                    $cacheTime = (int)($baseCacheTime * $randomFactor); // 计算缓存时间
                     Redis::setex($cacheKey, $cacheTime, json_encode($response));

                    return ['status' => 1, 'data' =>$response];

                }


            } finally {
                // 确保释放锁
                Redis::del($lockKey);
            }
        } else {
            // 如果没有获取到锁，等待缓存生成后再返回
            while (!Redis::exists($cacheKey)) {
                usleep(100 * 1000); // 等待 100 毫秒
            }
            $cachedData = Redis::get($cacheKey);
            return ['status' => 1, 'data' => json_decode($cachedData, true)];

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
        $relative_path = $upload_res['absolute_path'];
        return $relative_path;
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
}