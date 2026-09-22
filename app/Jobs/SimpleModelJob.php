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
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class SimpleModelJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;


    public $tries = 1;
    public $timeout = 1800;

    public $uniacid;

    public $option_id;
    public $total_face;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($option_id, $uniacid,$total_face=0)
    {
        $this->option_id = $option_id;
 
        $this->uniacid = $uniacid;

        $this->total_face = $total_face;

    }
    



    /**
     * @return bool|void
     */
    public function handle()
    {
        try {
           
              //  if($this->total_face != $goodsOption->total_face){
            $result  = $this->processModelById();
            if ($result['success']) {
                \Log::debug('模型单次处理成功', $result['data']);
            } else {

                \Log::error('模型单次处理失败', $result['error']);
            }
     
        } catch (\Exception $e) {
            $error = "处理异常: " . $e->getMessage();
            Log::error($error);
            throw $e;
        }
    }




    /**
     * 通过商品选项ID处理模型
     * 
     * @param int $optionId 商品选项ID
     * @return array
     */
    private function processModelById()
    {
        try {
            $response = Http::timeout(300) // 5分钟超时
                ->post('http://3d.abangmi.com/process_model', [
                    'option_id' => $this->option_id,
                    'faces' => $this->total_face
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            } else {
                \Log::error('API调用失败', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                return [
                    'success' => false,
                    'error' => $response->body()
                ];
            }
        } catch (\Exception $e) {
            \Log::error('调用API时发生异常', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }


    
}