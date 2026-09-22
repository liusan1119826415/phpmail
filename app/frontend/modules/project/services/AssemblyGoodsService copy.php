<?php


namespace app\frontend\modules\project\services;


use app\backend\modules\goods\models\GoodsOption;
use app\backend\modules\goods\models\GoodsSpec;
use app\backend\modules\goods\models\GoodsSpecItem;
use app\common\exceptions\ShopException;
use app\common\facades\Setting;
use app\common\models\Goods;
use app\common\models\goods\ProductTemplate;
use app\common\services\upload\UploadService;
use app\Jobs\DispatchesJobs;
use app\Jobs\AssemblyGoodsJob;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Illuminate\Support\Facades\File;
use Yunshop\Supplier\common\models\Supplier;

class AssemblyGoodsService
{
    public function assembleDwg($data,$is_edit)
    {

        return self::assembly($data,$is_edit);

    }


    public static function uploadOss($save_path, $file_name)
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


    public static function assembly($data,$is_edit)
    {
        if($is_edit && $data['cart_id']){
            //编辑空间
            return self::editGoods($data);
        }elseif(!$is_edit && !$data['cart_id']){
            //生成新产品
            
            return self::addGoods($data);
        }else{
            return self::editGoods($data);
        }

    }


    public static function editGoods($data)
    {
        $goods_id = $data['goods_id'];
        $optionIds = $data['optionIds'];
        $jsonData = $data['jsonData'];
        $base64thumb = $data['base64thumb'];
        $goodsModel = Goods::uniacid()->find($goods_id);
        if (!$goodsModel) {
            return false;
        }

        /*$optionDataIds = collect($optionIds)
            ->groupBy(function ($item) { return $item; })
            ->map(function ($group, $optionId) {
                return [
                    "option_id" => $optionId,
                    "num" => $group->count()
                ];
            })
            ->values()
            ->toArray();*/

        $thumbdata = self::uploadOssThumb($base64thumb);
        $thumb = $thumbdata['thumb'];
        $goodsModel->type2 = 2; //组合商品
        $goodsModel->productType = $data['productType']; //模型类型
        $goodsModel->old_id = $goods_id;
        $goodsModel->old_option = serialize($data['optionData']);
        $goodsModel->thumb = $thumb;
        if($data['goods_uuid']){
            $goodsModel->goods_uuid = $data['goods_uuid'];
        }
        $goodsModel->save();

        $oldGoodsOptions = GoodsOption::whereIn('id',$optionIds)->get();


        $optionCounts = array_count_values($optionIds);
        // 初始化累加值
        $totalLength = 0;
        $totalWidth = 0;
        $totalHeight = 0;
        $totalProductPrice = 0;
        $totalStock = 0;
        $totalVolume = 0;
        $product_model = "";
        $package_number = 0;
        $package_option = [];
        $structure = "";
        $allStructuresSame = true;
        $previousStructure = null;
        foreach ($oldGoodsOptions as $item){
            $optionId = $item->id;
            $repeatCount = $optionCounts[$optionId] ?? 1;  // 该 ID 在 optionIds 中的重复次数

            // 累加 length, width, height
            $totalLength += ($item->length ?? 0) * $repeatCount;
            $totalWidth = $item->width;
            $totalHeight = $item->height;
            $totalProductPrice += ($item->product_price ?? 0) * $repeatCount;
            $totalStock += $item->stock * $repeatCount;
            $totalVolume += $item->volume * $repeatCount;

            // 拼接 product_model
            if (!empty($item->product_model)) {
                $product_model .= str_repeat($item->product_model . "|", $repeatCount);
            }

            // 检查所有 structure 是否相同
            if ($previousStructure === null) {
                $previousStructure = $item->structure;
            } else {
                if ($item->structure != $previousStructure) {
                    $allStructuresSame = false;
                }
            }

            // 累加 package_number
            $package_number += $item->package_number * $repeatCount;

            // 反序列化 package_option 并合并
            $arr = unserialize($item->package_option);
            if (is_array($arr)) {
                for ($i = 0; $i < $repeatCount; $i++) {
                    $package_option = array_merge($package_option, $arr);
                }
            }
        }

        // 如果所有 structure 相同，则直接取其中一个（或做其他处理）
        if ($allStructuresSame && !empty($oldGoodsOptions)) {
            $structure = $oldGoodsOptions[0]->structure;
        } else {
            // 否则，您可以按原逻辑累加（如果是数字）或拼接（如果是字符串）
            // 但注意：原代码是累加，所以这里保持累加
            foreach ($oldGoodsOptions as $item) {
                $optionId = $item->id;
                $repeatCount = $optionCounts[$optionId] ?? 1;
                // 如果是字符串拼接，则改为：
                $structure .= str_repeat($item->structure, $repeatCount);
            }
        }

        $product_model = rtrim($product_model, '|');
        $newOption = [
            'length' => $totalLength,
            'width' => $totalWidth,
            'height' => $totalHeight,
            "title" => $totalLength . "*" . $totalWidth . "*" . $totalHeight,
            "product_price" => $totalProductPrice,
            "market_price" => $totalProductPrice,
            "stock" => $totalStock / 2 ?: 0,
            "weight" => 0,

            "volume" => $totalVolume,
            "product_model" => $product_model,
            'package_number' => $package_number,
            'package_option' => serialize($package_option),
            'd3model' => "",
            'virtual' => 0,
            'modelType' => 0, //独立位
            'singleType' => 0, //默认值
            "red_price" => '',
            'thumb'=>$thumb,
            'thumb_url'=>serialize($thumbdata),
            'structure'=>$structure,
            'productType'=>$data['productType']
        ];

        GoodsOption::where('goods_id',$goods_id)->update($newOption);
        $ProductTemplate = ProductTemplate::where('goods_id', $goods_id)->first();
        $template_dwg = yz_tomedia($ProductTemplate->template_dwg);
        $uniqid = uniqid();
        $params = [
            "jsondata" => $jsonData,
            "template" => $template_dwg,
            "mtype" => 1,
            "output" => storage_path("app/public/tmp") . "/" . $uniqid . ".png",
            "output_dwg" => storage_path("app/public/tmp") . "/" . $uniqid . ".dwg"
        ];
        $job = new AssemblyGoodsJob($goodsModel->id, $goodsModel->uniacid, $params, $base64thumb);
        DispatchesJobs::dispatch($job, DispatchesJobs::LOW);
        return ['goods_id' => $goods_id, 'option_id' => 0];



    }

    public static function addGoods($data)
    {

        $goods_id = $data['goods_id'];
        $optionIds = $data['optionIds'];
   
        $jsonData = $data['jsonData'];
        $base64thumb = $data['base64thumb'];
        $goodsModel = Goods::uniacid()->find($goods_id);
        if (!$goodsModel) {
            return false;
        }

        $newGoods = $goodsModel->replicate(['show_sales']);
        $copy_real_sales = Setting::get('goods.copy_real_sales');

        if (is_numeric($copy_real_sales) && !$copy_real_sales) {
            $newGoods->real_sales = 0;
        }
        $thumb = "";
        $hight_url = "";
        if($base64thumb){
            $thumb_data = self::uploadOssThumb($base64thumb);
        }
        
        /*$optionDataIds = collect($optionIds)
            ->groupBy(function ($item) { return $item; })
            ->map(function ($group, $optionId) {
                return [
                    "option_id" => $optionId,
                    "num" => $group->count()
                ];
            })
            ->values()
            ->toArray();*/

        $newGoods->type2 = 2; //组合商品
        $newGoods->old_id = $goods_id;
        $newGoods->productType = $data['productType']; //单模型
        $newGoods->old_option = serialize($data['optionData']);
    
        if($thumb_data['thumb']){
            $newGoods->thumb = $thumb_data['thumb'];
        }
        $newGoods->save();

       
        $goodsModel->setRelations([]);

        $goodsModel->load('hasOneShare', 'hasManyDiscount', 'hasOneSale', 'hasOneGoodsDispatch', 'hasOnePrivilege');
        foreach ($goodsModel->getRelations() as $relation => $item) {
            if ($item) {
                if ($relation == 'hasManyDiscount') {
                    foreach ($item as $val) {
                        unset($val->id);
                        $val->setRelations([]);
                        $newGoods->{$relation}()->create($val->toArray());
                    }
                    continue;
                }
                unset($item->id);
                $item->setRelations([]);
                $newGoods->{$relation}()->create($item->toArray());
            }
        }

        $goodsModel->setRelations([]);
        // 查询指定的 hasManyOptions 数据 (例如 optionIds = [165, 166])
        $goodsModel->load(['hasManyParams', 'hasManyOptions' => function ($query) use ($optionIds) {
            $query->whereIn('id', $optionIds);  // 只查询指定的 optionIds
        }]);
        // 计算 optionIds 中每个 ID 出现的次数
        $optionCounts = array_count_values($optionIds);
        // 初始化累加值
        $totalLength = 0;
        $totalWidth = 0;
        $totalHeight = 0;
        $totalProductPrice = 0;
        $totalStock = 0;
        $totalVolume = 0;
        $product_model = "";
        $package_number = 0;
        $package_option = [];
        $structure = "";
        $allStructuresSame = true;
        $previousStructure = null;
        $structureValues = []; // 用于存储所有的 structure 值
        foreach ($goodsModel->getRelations() as $relation => $items) {
            foreach ($items as $item) {
                if ($item && $relation === 'hasManyOptions') {
                    $optionId = $item->id;
                    $repeatCount = $optionCounts[$optionId] ?? 1;  // 该 ID 在 optionIds 中的重复次数

                    // 累加 length, width, height
                    $totalLength += ($item->length ?? 0) * $repeatCount;
                    $totalWidth = $item->width;
                    $totalHeight = $item->height;
                    $totalProductPrice += ($item->product_price ?? 0) * $repeatCount;
                    $totalStock += $item->stock * $repeatCount;
                    $totalVolume += $item->volume * $repeatCount;

                    // 拼接 product_model
                    if (!empty($item->product_model)) {
                        $product_model .= str_repeat($item->product_model . "|", $repeatCount);
                    }

                    // 收集 structure 值用于判断
                    for ($i = 0; $i < $repeatCount; $i++) {
                        $structureValues[] = $item->structure;
                    }

                    // 检查所有 structure 是否相同
                    if ($previousStructure === null) {
                        $previousStructure = $item->structure;
                    } else {
                        if ($item->structure != $previousStructure) {
                            $allStructuresSame = false;
                        }
                    }

                    // 累加 package_number
                    $package_number += $item->package_number * $repeatCount;

                    // 反序列化 package_option 并合并
                    $arr = unserialize($item->package_option);
                    if (is_array($arr)) {
                        for ($i = 0; $i < $repeatCount; $i++) {
                            $package_option = array_merge($package_option, $arr);
                        }
                    }

                } else {
                    unset($item->id);
                    $item->setRelations([]);
                    $newGoods->{$relation}()->create($item->toArray());
                }
            }


            // 创建一条合成后的数据
            if ($relation === 'hasManyOptions') {
                $product_model = rtrim($product_model, '|');

                // 根据 structure 是否相同来决定如何处理
                if ($allStructuresSame && !empty($structureValues)) {
                    // 所有 structure 相同，使用第一个值
                    $finalStructure = $structureValues[0];
                } else {
                    // structure 不相同，这里可以根据需求处理：

                    // 例如拼接所有不同的值：
                     $finalStructure = implode(' ', array_unique($structureValues));
                }
                $newOption = [
                    'length' => $totalLength,
                    'width' => $totalWidth,
                    'height' => $totalHeight,
                    // 可以加入其他需要的字段
                    'thumb'=>$thumb,
                    'thumb_url'=>serialize($thumb_data),
                    "uniacid" => $newGoods->uniacid,
                    "goods_id" => $newGoods->id,
                    "title" => $totalLength . "*" . $totalWidth . "*" . $totalHeight,
                    "product_price" => $totalProductPrice,
                    "cost_price" => 0,
                    "market_price" => $totalProductPrice,
                    "stock" => $totalStock / 2 ?: 0,
                    "weight" => 0,
                    "volume" => $totalVolume,
                    "product_model" => $product_model,
                    'package_number' => $package_number,
                    'package_option' => serialize($package_option),
                    'd3model' => "",
                    //"specs" => implode('_',$specs_id_array),
                    'virtual' => 0,
                    'modelType' => 0, //独立位
                    'singleType' => 0, //默认值
                    "red_price" => '',
                    'structure' => $finalStructure, // 这里添加 structure 字段
                    
                ];

                // 将合成后的数据插入到新的 hasManyOptions
                $newGoods->{$relation}()->create($newOption);
            }
        }

        $goodsModel->setRelations([]);
        $goodsModel->load('hasManyGoodsCategory');
        foreach ($goodsModel->getRelations() as $relation => $items) {
            foreach ($items as $item) {
                if ($item) {
                    unset($item->id);
                    $item->goods_id = $newGoods->id;
                    $item->setRelations([]);
                    $newGoods->{$relation}()->create($item->toArray());
                }
            }
        }
        //todo, 先复制老的规格,再复制规格项,再更新规格content字段,最后复制option,更新option specs字段
        $goodsSpecs = GoodsSpec::uniacid()->where('goods_id', $goodsModel->id)->get();

        $specItemIds = [];
        $item_ids = [];
        $spec_id = 0;
        foreach ($goodsSpecs as $goodsSpec) {
            $newGoodsSpecModel = $goodsSpec->replicate();
            $newGoodsSpecModel->goods_id = $newGoods->id;

            $newGoodsSpecModel->save();

            //获取旧的规格项
            $goodsSpecItems = GoodsSpecItem::uniacid()->where("specid", $goodsSpec->id)->get();

            foreach ($goodsSpecItems as $goodsSpecItem) {
                $newGoodsSpecItem = $goodsSpecItem->replicate();
                $newGoodsSpecItem->specid = $newGoodsSpecModel->id;
                $newGoodsSpecItem->save();

                $items = [
                    'old_item' => $goodsSpecItem->id,
                    'new_item' => $newGoodsSpecItem->id,
                ];
                $spec_id = $newGoodsSpecItem->id;

                array_push($item_ids, $items);
                array_push($specItemIds, $newGoodsSpecItem->id);
            }

            $newGoodsSpecModel->content = serialize($specItemIds);
            $newGoodsSpecModel->save();
        }


        $goodsOption = GoodsOption::uniacid()->where('goods_id', $newGoods->id)->first();

        $goodsOption->specs = $spec_id;
        $goodsOption->save();

        $member_id = Supplier::where('id',$newGoods->supp_id)->value('member_id');
        \Yunshop\Supplier\common\models\SupplierGoods::create([
            'goods_id'    => $newGoods->id,
            'supplier_id' => $newGoods->supp_id,
            'member_id'   => $member_id
        ]);

        $ProductTemplate = ProductTemplate::where('goods_id', $goods_id)->first();
        $template_dwg = yz_tomedia($ProductTemplate->template_dwg);
        $uniqid = uniqid();
        $params = [
            "jsondata" => $jsonData,
            "template" => $template_dwg,
            "mtype" => 1,
            "output" => storage_path("app/public/tmp") . "/" . $uniqid . ".png",
            "output_dwg" => storage_path("app/public/tmp") . "/" . $uniqid . ".dwg"
        ];
        $job = new AssemblyGoodsJob($newGoods->id, $newGoods->uniacid, $params, $base64thumb);
        DispatchesJobs::dispatch($job, DispatchesJobs::LOW);
      
        return ['goods_id' => $newGoods->id, 'option_id' => $goodsOption->id];
    }



    protected static function uploadOssThumb($input)
    {
        $uniqid = uniqid();
        // If $input is base64, decode and save as an image file
        $save_path = storage_path("app/public/tmp") . "/" . $uniqid . ".png";
        self::decodeAndSaveBase64($input, $save_path);


        $upload_res = uploadOssV2($save_path,$uniqid . ".png",1,'thumb');

        unlink($save_path);
        $image_url = $upload_res['webp_thumb_absolute_path'];
        $high_url = $upload_res['high_absolute_path'];
        return [
            'thumb'=>$image_url,
            'main_thumb'=>$high_url
        ];
    }


    protected static function decodeAndSaveBase64($base64Str, $savePath)
    {
        // 检查是否包含 'data:image/...' 前缀
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Str, $matches)) {
            // 如果包含，去掉前缀部分并解码

            $base64Str = substr($base64Str, strpos($base64Str, ',') + 1);
        }
        // 解码 Base64 数据
        $fileContents = base64_decode($base64Str);


        // 保存到指定路径
        File::put($savePath, $fileContents);

    }


    public static function processDwg($api_method, $params)
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