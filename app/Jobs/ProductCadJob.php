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
use app\common\models\goods\ProductTemplate;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ProductCadJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $goods_id;
    public $tries = 1;
    public $timeout = 300;
    protected $goodsOptionIds;

    protected $cadPlanModels;

    protected $originalCadModels;

    const BLOCK_PREFIX = "ABANGMI#";

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($goods_id, $goodsOptionIds,$originalCadModels,$cadPlanModels,$uniacid)
    {
        $this->goodsOptionIds = $goodsOptionIds;
        $this->goods_id = $goods_id;
        $this->uniacid = $uniacid;
        $this->cadPlanModels = $cadPlanModels;

        $this->originalCadModels = $originalCadModels;


    }

    /**
     * @return bool|void
     */
    public function handle()
    {
        \YunShop::app()->uniacid = Setting::$uniqueAccountId = $this->uniacid;
        return $this->setAutoCad();
    }

    private function setAutoCad()
    {
        $output = storage_path("app/public/tmp");
        $jsondata = [];
        $hasChanges = false; // 新增标志位，用于记录是否有变更
        $options = GoodsOption::select('id', 'goods_id', 'cad_plan_model','block_name')->whereIn("id", $this->goodsOptionIds)->get();
        if ($options) {
            foreach ($options as $object) {

                $block_name = self::BLOCK_PREFIX . $object->goods_id . "#" . $object->id;
                $originalModel = $this->originalCadModels[$object->id] ?? null;

                $newModel = $this->cadPlanModels[$object->id] ?? null;
                // 判断当前记录的cad_plan_model是否与传入的相同
                if($originalModel === $newModel && $object->block_name == $block_name) {
                    // 如果相同，跳过处理
                    \Log::error("cad模版内容相同");
                    continue;
                }

                $hasChanges = true; // 标记有变更

                $params = [
                    'url' => yz_tomedia($object->cad_plan_model),
                    'new_block_name' => $block_name,
                    "output" => $output,
                ];
                $api_method = "process-dwg";
                $result = $this->processDwg($api_method, $params);
                if ($result['status'] == 1 && $result['data']['status_code'] == 200) {

                    $extension = pathinfo($object->cad_plan_model, PATHINFO_EXTENSION);
                    $save_path = $output . "/" . $block_name . "." . $extension;
                    $file_name = $block_name . "." . $extension;
                    $relative_path = $this->uploadOss($save_path, $file_name);
                    $object->block_name = $block_name;
                    $object->cad_plan_model = $relative_path;
                    $object->save();
                    $jsondata[] = [
                        "block_name" => $block_name,
                        "url" => yz_tomedia($relative_path)
                    ];

                }else{

                    $jsondata[] = [
                        "block_name" => $block_name,
                        "url" => yz_tomedia($object->cad_plan_model)
                    ];
                    $object->block_name = $block_name;
                    $object->save();
                }

            }
        }

        //合并模版
        // 只有当有变更时才合并模板
        if ($hasChanges && !empty($jsondata)) {
            $params = [
                "jsondata" => $jsondata,
                "output" => $output
            ];

            $result = $this->processDwg("process_template", $params);
            if ($result['status'] == 1) {
                $template = $result['data']['template'];
                $save_path = $output . "/" . $template;
                $relative_path = $this->uploadOss($save_path, $template);
                $ProductTemplate = ProductTemplate::where('goods_id',$this->goods_id)->first();
                if($ProductTemplate){
                    $ProductTemplate->template_dwg = $relative_path;
                    $ProductTemplate->save();
                }else{
                    ProductTemplate::create([
                        "goods_id"=>$this->goods_id,
                        "template_dwg"=>$relative_path
                    ]);
                }



            }
        }


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