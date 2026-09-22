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

class GoodsImageJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;


    public $tries = 1;



    public $timeout = 24000;


    public $atlas;


    public $uniacid;

    public $goods_id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($atlas,$goods_id,$uniacid)
    {
         $this->atlas = $atlas;
         $this->goods_id = $goods_id;
         $this->uniacid = $uniacid;
    }

    /**
     * @return bool|void
     */
    public function handle()
    {
        \YunShop::app()->uniacid = Setting::$uniqueAccountId = $this->uniacid;
        $goods = Goods::uniacid()->where('id',$this->goods_id)->first();
        $atlas_new = $this->atlas;
        if(md5($goods->atlas) == md5(json_encode($this->atlas))){
            \Log::error("===图册数据相等不需要修改===");
            return true;
        }
         if($this->atlas['isPdf'] == 1){
             $pdf_url = yz_tomedia($this->atlas['pdfUrl']);
             \Log::debug("====pdf_url===",$pdf_url);
             // 准备需要处理的页码和对应的inPpt状态
             $pdfPages = [];
             $inPptStatus = [];
             foreach ($this->atlas['data'] as $item) {
                 $pdfPages[] = $item['pdfPage'];
                 $inPptStatus[] = $item['inPpt'] ?? 0; // 默认为0
             }

             $params = [
                 'pdf_file' => $pdf_url,
                 'output' => storage_path("app/public/pdfimg"),
                 'pdf_data' => [
                     'pages' => $pdfPages,
                     'in_ppt' => $inPptStatus // 传递inPpt状态
                 ]
             ];
             $processedData = processDwg("pdfimg", $params);
             \Log::debug("=====processedData=====",$processedData);
             $result = [];
             if ($processedData['data']['status_code'] == 200) {
                 $image_ppt_path = array_column($processedData['data']['image_paths'],'image_high_path');

                 foreach ($processedData['data']['image_paths'] as $item) {
                     $res = uploadOssV2($item['image_path'], uniqid() . ".png", 1);
                     $arr = [
                         'thumb_link' => $res['absolute_path'],
                         'thumb' => "",
                         'main_thumb' => $res['relative_path'],
                         'high_url' => $res['high_relative_path'],
                         'inPpt' => $item['in_ppt'] // 从API返回中获取
                     ];

                     $result[] = $arr;
                 }
             }


             $atlas_data = json_encode(['data'=>$result,'isPdf'=>0]);
             $goods->e_catalog_pdf = $pdf_url;
             $goods->pdf_page_img = $image_ppt_path?serialize($image_ppt_path):serialize([]);
         }elseif($this->atlas['isPdf'] == 0){
             $atlas_data = json_encode($atlas_new);
             //根据图片生成pdf 并上传oss

             // 检查是否需要生成PDF
             $needGeneratePdf = false;

             // 获取当前的高清URL数组
             $currentHighUrls = array_map(function($item) {
                 return yz_tomedia($item['high_url']);
             }, $this->atlas['data']);

             $dbHighUrls = [];
             $jsonAtlas = json_decode($goods->atlas,true);
             if (!empty($jsonAtlas) && is_array($jsonAtlas) && isset($jsonAtlas['data'])) {
                 $dbHighUrls = array_map(function($item) {
                     return yz_tomedia($item['high_url']);
                 }, $jsonAtlas['data']);
             }

             // 比较两个数组是否相同
             if ($currentHighUrls != $dbHighUrls) {
                 \Log::error("===两组数据不同===999000");
                 $needGeneratePdf = true;
             }

             // 只有当高清URL有修改时才生成PDF
             if ($needGeneratePdf) {



                 $downloadedImages = $this->downPptImg($atlas_new);

                 // 根据图片生成pdf并上传oss
                 $output = storage_path("app/public/pdfimg/atlas.pdf");
                 $params = [
                     'image_urls' => $currentHighUrls,
                     'output_pdf' => $output,
                 ];
                 $response = processDwg("generatePdf", $params);
                 if ($response['data']['status_code'] != 200) {
                     \Log::error("生成 PDF 失败: " . json_encode($response));
                     return false;
                 }
                 $res = uploadOss($output, "atlas.pdf", 0);
                 if (!$res || !isset($res['absolute_path'])) {
                     \Log::error("上传 OSS 失败");
                     return false;
                 }
                 if($downloadedImages){
                     $goods->pdf_page_img = serialize($downloadedImages);
                 }

                 $goods->e_catalog_pdf = yz_tomedia($res['absolute_path']);


             }

         }
         $goods->atlas = $atlas_data;
         $goods->save();




    }


    private function downPptImg($atlas_new)
    {
        // 1. 先筛选出 inPpt == 1 的项
        $filteredData = array_filter($atlas_new['data'], function($item) {
            return isset($item['inPpt']) && $item['inPpt'] == 1;
        });

        // 2. 提取 high_url 并用 yz_tomedia 处理
        $highUrls = array_map(function($item) {
            return yz_tomedia($item['high_url']);
        }, $filteredData);

        // 准备参数传给Python

        if(!$highUrls){
            return [];
        }
        $params = [
            'image_urls' => $highUrls,
        ];

        // 调用Python接口下载图片
        $response = processDwg("downloadHighImages", $params);

        if ($response['data']['status_code'] != 200) {
            \Log::error("下载高清图片失败: " . json_encode($response));
            return false;
        }

        // 获取下载的图片路径
        $downloadedImages = $response['data']['downloaded_images'] ?? [];

        return $downloadedImages;
    }

}