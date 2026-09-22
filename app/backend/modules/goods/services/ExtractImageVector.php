<?php


namespace app\backend\modules\goods\services;


use app\backend\modules\goods\models\GoodsOption;
use app\common\facades\Setting;
use app\common\models\UniAccount;
use app\backend\modules\goods\models\Goods;
class ExtractImageVector
{

    public $uniacid;

    public function handle()
    {
        $uniAccount = UniAccount::getEnable();
        foreach ($uniAccount as $u) {
            \YunShop::app()->uniacid = $u->uniacid;
            Setting::$uniqueAccountId = $u->uniacid;
            $this->uniacid = $u->uniacid;
            $this->runTask();
        }
    }

    public function runTask(){
         /*$goodsList = Goods::select("id","thumb","thumb_url","real_image")->uniacid()->where('status',1)->where('type2',1)->where('plugin_id',92)->with(['hasManyOptions'=>function($query){
             $query->select("id","goods_id","thumb");
         }])->get();*/

         $goodsOptionList = GoodsOption::select("id","goods_id","thumb")->uniacid()->where('is_default',1)->with(['goods'=>function($query){
             $query->select("id","thumb_url","real_image");
         }])->whereHas('goods',function ($query){
             $query->where('status',1)->where('type2',1);
         })->get();
         foreach ($goodsOptionList as $goods_model){
            $this->vector($goods_model);
         }
    }

    private function isOneDimensionalArray($array): bool
    {
        if($array){
            foreach ($array as $item) {
                if (is_array($item)) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function vector($goods_model)
    {



        $images = [
            [
                'goods_id' => $goods_model->goods_id,
                'option_id'=>$goods_model->id,
                'url' => yz_tomedia($goods_model->thumb)
            ]
        ];

       /* if (!empty($goods_model->goods->thumb_url)) {
            $thumb_imgs = $goods_model->goods->thumb_url?unserialize($goods_model->goods->thumb_url):[];
            $is_one_arr = $this->isOneDimensionalArray($thumb_imgs);
            if($thumb_imgs){
                foreach ($thumb_imgs as $v) {
                    // 追加到 $images 数组
                    array_push($images, [
                        'goods_id' => $goods_model->goods_id,
                        'url' => $is_one_arr?yz_tomedia($v):yz_tomedia($v['thumb']),
                        'option_id'=>$goods_model->id
                    ]);
                }
            }

        }

        if (!empty($goods_model->goods->real_image)) {
            $real_images = $goods_model->goods->real_image?unserialize($goods_model->goods->real_image):[];
            $is_one_arr = $this->isOneDimensionalArray($real_images);
            if($real_images){
                foreach ($real_images as $v) {
                    // 追加到 $images 数组

                    array_push($images, [
                        'goods_id' => $goods_model->goods_id,
                        'url' => $is_one_arr?yz_tomedia($v):yz_tomedia($v['thumb']),
                        'option_id'=>$goods_model->id
                    ]);
                }
            }

        }*/




           /* if($goods_model->hasManyOptions) {
                foreach ($goods_model->hasManyOptions as $key => $val) {
                    if ($val->thumb) {
                        array_push($images, [
                            'goods_id' => $goods_model->id,
                            'url' => yz_tomedia($val->thumb)
                        ]);
                    }
                }
            }*/


        $apiUrl = config('app.SEARCH_URL').'/myapp/index/search/batch_milvus_img';
        $is_type = 2;  //测试平台

        // 创建 POST 数据，params 为包含多个图片信息的数组
        $postData = [
            'params' => json_encode($images),
            'type'=>$is_type,
            'goods_id'=>$goods_model->goods_id,
            'option_id'=>$goods_model->id
        ];

        // 初始化 cURL
        $ch = curl_init($apiUrl);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        if ($response === false) {
            echo "cURL error: " . curl_error($ch);
            \Log::debug("cURL error: " . curl_error($ch));
        } else {
            \Log::debug("Response from API: " . $response);
        }

        curl_close($ch);
    }



}