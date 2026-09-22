<?php

namespace app\common\traits;

use Yunshop\Supplier\common\models\SupplierColorPlane;
use app\frontend\models\GoodsOption;
use app\common\models\GoodsSpecItem;
use app\backend\modules\goods\models\Category;
use app\common\models\GoodsSpec;
use Yunshop\Supplier\common\models\CategoryGoodsSort;
use Yunshop\Supplier\common\models\GoodsCategorySort;

trait ProcessGoodsOptionTrait
{


    public function configureIndex()
    {
        // 1. 获取所有二级分类 ID
        $categoryIds = Category::where('level', 2)->pluck('id')->toArray();
        // 2. 生成扁平化排序字段名
        $sortCatFields = array_map(fn($id) => "sort_cat_{$id}", $categoryIds);
        $this->index->updateSettings([
            'searchableAttributes' => [
                'title',
                'goods_title',
                'option_title',
                'category_name',
                'supplier_name',
                //  'material_name',
                //  'style_name',

            ],
            'filterableAttributes' => [
                'category_id',
                'supplier_id',
                'material_id',
                'style_id',
                'supplier_city_id',
                'bid_enable',
                'is_stock',
                'lead_time',
                'price',
                'status',
                'enable',
                'goods_id',
                'length',
                'thumb3dModelUrl',
                'is_discount',
                'is_hot',
                'id',
                'best_category_id'
            ],
            'sortableAttributes' => array_merge([
                'price',
                'id',
                'show_sales',
                'comment_num',
                'created_at',
                'display_order',
                'category_sort',
                'option_order_sort',
                'best_category_final_sort',
                'best_category_last_sort',
                'best_category_sort',
                'best_category_two_last_sort',
            ], $sortCatFields), // 把所有 sort_cat_* 加进去
            'rankingRules' => [
                'sort',
                'exactness',
                'words',
                'proximity',
                'attribute',
                'typo'

            ],


        ]);
    }



     // 'rankingRules' => [
            //     /*'typo',
            //     'words',
            //     'proximity',
            //     'attribute',
            //     'exactness',
            //     'sort',*/
            //     'exactness',
            //     'words',
            //     'proximity',
            //     'attribute',
            //     'typo',
            //     'sort'
            // ],
            
            // 'synonyms' => [

            //     // === 办公椅类 ===
            //     '办公椅' => ['转椅', '职员椅', '电脑椅', '写字椅', '网布椅', '办公座椅', '办公转椅', '人体工学椅', '椅子'],
            //     '转椅' => ['办公椅', '职员椅', '电脑椅', '写字椅', '网布椅'],
            //     '职员椅' => ['办公椅', '转椅', '电脑椅', '写字椅', '办公座椅'],
            //     '电脑椅' => ['办公椅', '转椅', '职员椅', '写字椅', '人体工学椅'],
            //     '人体工学椅' => ['办公椅', '电脑椅', '职员椅', '转椅'],
            //     '办公椅' => ['办公座椅', '职员椅', '电脑椅', '写字椅', '办公转椅', '网布椅', '工学椅'],
            //     '会议椅' => ['会议座椅', '洽谈椅', '会客椅', '培训椅', '会议靠椅'],
            //     '老板椅' => ['大班椅', '行政椅', '经理椅', '主管椅', '皮椅', '真皮椅'],
            //     '休闲椅' => ['躺椅', '摇椅', '沙滩椅', '午休椅', '折叠椅'],
            //     '餐椅'   => ['饭椅', '餐桌椅', '就餐椅', '食堂椅', '餐厅椅'],
            //     '办公桌' => ['写字台', '电脑桌', '职员桌', '办公台', '办公台面'],
            //     '会议桌' => ['会议台', '长桌', '洽谈桌', '培训桌', '会议长台', '会客桌'],
            //     '老板桌' => ['大班桌', '行政桌', '经理桌', '主管桌', '老板台'],
            //     '前台桌' => ['接待台', '迎宾台', '收银台', '前台柜', '接待桌'],
            //     '餐桌'   => ['饭桌', '餐台', '餐桌台面', '餐厅桌'],

            //     '文件柜' => ['档案柜', '资料柜', '铁皮柜', '储物柜', '资料架', '文件箱'],
            //     '更衣柜' => ['衣柜', '储物柜', '收纳柜', '铁皮柜', '更换柜'],
            //     '书柜'   => ['书架', '资料柜', '展示柜', '文件柜'],
            //     '展示柜' => ['陈列柜', '玻璃柜', '展柜', '展示架'],
            //     '收纳柜' => ['储物柜', '整理柜', '收纳箱', '置物柜'],

            //     // === 办公桌类 ===
            //     '办公桌' => ['写字台', '电脑桌', '职员桌', '办公台', '办公台面', '办公台桌'],
            //     '写字台' => ['办公桌', '电脑桌', '职员桌', '办公台'],
            //     '电脑桌' => ['办公桌', '写字台', '职员桌', '办公台'],
            //     '职员桌' => ['办公桌', '写字台', '电脑桌'],

            //     // === 老板桌/行政桌 ===
            //     '老板桌' => ['大班桌', '行政桌', '经理桌', '主管桌'],
            //     '大班桌' => ['老板桌', '行政桌', '经理桌'],
            //     '行政桌' => ['老板桌', '大班桌', '经理桌'],
            //     '经理桌' => ['老板桌', '大班桌', '行政桌'],
            //     '班台' => ['大班台', '小班台'],

            //     // === 会议类 ===
            //     '会议桌' => ['会议台', '长桌', '洽谈桌', '会议台面'],
            //     '会议椅' => ['会议座椅', '洽谈椅', '会客椅', '会议用椅'],

            //     // === 沙发类 ===
            //     '办公沙发' => ['会客沙发', '休闲沙发', '接待沙发', '真皮沙发', '布艺沙发', '沙发'],
            //     '会客沙发' => ['办公沙发', '接待沙发', '休闲沙发'],
            //     '布艺沙发' => ['沙发', '办公沙发', '会客沙发', '布沙发'],
            //     '真皮沙发' => ['皮沙发', '办公沙发', '会客沙发'],

            //     // === 柜子类 ===
            //     '文件柜' => ['档案柜', '资料柜', '铁皮柜', '储物柜', '资料架'],
            //     '档案柜' => ['文件柜', '资料柜', '铁皮柜'],
            //     '资料柜' => ['文件柜', '档案柜', '铁皮柜'],
            //     '储物柜' => ['文件柜', '档案柜', '资料柜', '更衣柜'],
            //     '更衣柜' => ['储物柜', '衣柜', '铁皮柜', '更换柜'],

            //     // === 桌组组合 ===
            //     '工作站' => ['工位', '屏风位', '办公位', '职员位', '屏风工位'],
            //     '工位' => ['工作站', '屏风位', '办公位', '职员位'],
            //     '屏风位' => ['工作站', '工位', '办公位'],

            //     // === 前台 ===
            //     '前台桌' => ['接待台', '迎宾台', '收银台', '前台柜'],
            //     '接待台' => ['前台桌', '迎宾台', '接待桌'],
            //     '椅子凳子' => ['椅子', '凳子'],
            //     '椅凳' => ['椅子', '凳子', '办公椅'],
            //     // === 茶几/会议配套 ===
            //     '茶几' => ['会客几', '休闲几', '边几', '小桌几'],
            //     '书柜' => ['书架', '文件柜', '资料柜', '展示柜'],
            //     '办公柜' => ['文件柜', '储物柜', '档案柜', '展示柜'],

            //     // === 家具扩展（家用） ===
            //     '床' => ['单人床', '双人床', '高低床', '上下床', '大床'],
            //     '单人床' => ['床', '小床', '学生床'],
            //     '双人床' => ['床', '大床', '婚床'],
            //     '沙发床' => ['沙发', '床', '折叠床'],

            //     '餐桌' => ['饭桌', '吃饭桌', '餐台', '餐桌台面'],
            //     '饭桌' => ['餐桌', '餐台', '吃饭桌'],
            //     '餐椅' => ['饭椅', '餐桌椅', '椅子'],

            //     '茶几' => ['小桌子', '边桌', '会客几'],
            //     '衣柜' => ['储物柜', '更衣柜', '收纳柜'],

            //     // === 其他常用办公/家居 ===
            //     '书桌' => ['写字台', '办公桌', '电脑桌'],
            //     '躺椅' => ['休闲椅', '摇椅', '沙滩椅'],
            //     '展示柜' => ['陈列柜', '玻璃柜', '展柜'],
            //     '收纳柜' => ['储物柜', '整理柜', '收纳箱'],
            // ],
            // 'stopWords' => [
            //     '的',
            //     '了',
            //     '和',
            //     '与',
            //     '及',
            //     '是',
            //     '在',
            //     '有',
            //     '我',
            //     '你',
            //     '子',
            //     '他',
            //     '她',
            //     '它'
            // ]


    /**
     * 批量处理所有选项模型
     */
    public function processGoodsOptionBatch($goodsOptionModel)
    {
        // 第一步：一次性收集所有颜色ID
        $allColorIds = collect();

        foreach ($goodsOptionModel as $param) {
            $default_color = $this->safeUnserialize($param->default_color);
            $select_color = $this->safeUnserialize($param->select_color);

            // 收集default_color的ID
            if (isset($default_color['id'])) {
                $allColorIds->push($default_color['id']);
            }

            // 收集select_color中的所有ID
            if (is_array($select_color)) {
                foreach ($select_color as $item) {
                    if (isset($item['id'])) {
                        $allColorIds->push($item['id']);
                    }
                }
            }
        }

        // 去重并获取唯一颜色ID
        $uniqueColorIds = $allColorIds->unique()->values()->toArray();

        // 第二步：一次性查询所有颜色数据
        $colorDataMap = [];
        if (!empty($uniqueColorIds)) {
            $colorDataMap = $this->getColorDataMap($uniqueColorIds);

            // 批量处理所有图片URL（避免多次调用yz_tomedia）
            $thumbUrls = [];
            foreach ($colorDataMap as &$colorData) {
                if (!empty($colorData['thumb'])) {
                    $thumbUrls[$colorData['id']] = $colorData['thumb'];
                }
            }

            // 批量处理yz_tomedia
            $processedThumbs = [];
            foreach ($thumbUrls as $id => $thumb) {
                $processedThumbs[$id] = yz_tomedia($thumb);
            }

            // 批量处理getColorImageUrlSimple
            $processedHighUrls = [];
            foreach ($thumbUrls as $id => $thumb) {
                $processedHighUrls[$id] = $this->getColorImageUrlSimple($thumb);
            }

            // 更新colorDataMap
            foreach ($colorDataMap as &$colorData) {
                $id = $colorData['id'];
                $colorData['processed_thumb'] = $processedThumbs[$id] ?? '';
                $colorData['processed_high_url'] = $processedHighUrls[$id] ?? '';
            }
        }

        // 第三步：批量处理所有选项
        $result = $goodsOptionModel->map(function ($param) use ($colorDataMap) {
            // 反序列化数据
            $default_color = $this->safeUnserialize($param->default_color);
            $select_color = $this->safeUnserialize($param->select_color);

            // 处理default_color
            if (is_array($default_color) && !empty($default_color)) {
                $default_color = $this->processSingleColorBatch($default_color, $colorDataMap);
            } else {
                $default_color = [];
            }

            // 处理select_color
            $filteredSelectColor = [];
            if (is_array($select_color)) {
                foreach ($select_color as $item) {
                    $processedColor = $this->processSingleColorBatch($item, $colorDataMap);
                    if (!empty($processedColor)) { // 注意：原方法返回[]表示不存在，这里判断!empty
                        $filteredSelectColor[] = $processedColor;
                    }
                }
            }

            // 赋值给原对象
            $param->select_color = $filteredSelectColor;
            $param->default_color = $default_color;
            $param->map_param = $this->safeUnserialize($param->map_param);

            return $param;
        });

        return $result;
    }

    /**
     * 批量版的processSingleColor
     */
    private function processSingleColorBatch($color, $colorDataMap)
    {
        // 检查颜色ID是否存在（原方法的逻辑）
        if (!isset($color['id']) || !isset($colorDataMap[$color['id']])) {
            return []; // 颜色不存在，返回空数组（与原方法一致）
        }

        $colorId = $color['id'];
        $colorData = $colorDataMap[$colorId];

        // 使用预处理好的图片URL
        $color['thumb'] = $colorData['processed_thumb'] ?? '';
        $color['high_url'] = $colorData['processed_high_url'] ?? '';
        $color['name'] = $colorData['name'] ?? '';

        // 移除文件扩展名（原方法的逻辑）
        if (isset($color['name'])) {
            $color['name'] = preg_replace('/\.(jpg|jpeg|png|gif|bmp|webp|svg|ico)$/i', '', $color['name']);
        }

        return $color;
    }



    /**
     * 为了兼容原有调用，保留原方法但调用批量版
     */
    public function processGoodsOption($goodsOptionModel)
    {
        // 如果传入的是集合，直接使用批量处理
        if (
            $goodsOptionModel instanceof \Illuminate\Support\Collection ||
            $goodsOptionModel instanceof \Illuminate\Database\Eloquent\Collection
        ) {
            return $this->processGoodsOptionBatch($goodsOptionModel);
        }

        // 如果传入的是单个模型，包装成集合处理
        return $this->processGoodsOptionBatch(collect([$goodsOptionModel]));
    }

    // public function processGoodsOption($goodsOptionModel)
    // {
    //     $allColorIds = $goodsOptionModel->flatMap(function ($param) {
    //         $colorIds = [];
    //         $default_color = $this->safeUnserialize($param->default_color);
    //         $select_color = $this->safeUnserialize($param->select_color);

    //         if (isset($default_color['id'])) {
    //             $colorIds[] = $default_color['id'];
    //         }

    //         if (is_array($select_color)) {
    //             foreach ($select_color as $item) {
    //                 if (isset($item['id'])) {
    //                     $colorIds[] = $item['id'];
    //                 }
    //             }
    //         }

    //         return $colorIds;
    //     })->unique()->toArray();

    //     // 一次性查询所有颜色数据
    //     $colorDataMap = [];
    //     if (!empty($allColorIds)) {
    //         $colorDataMap = $this->getColorDataMap($allColorIds);
    //     }

    //     $result =  $goodsOptionModel->map(function ($param) use ($colorDataMap) {
    //         $default_color = $this->safeUnserialize($param->default_color);
    //         $select_color = $this->safeUnserialize($param->select_color);

    //         // 处理颜色数据
    //         $default_color = $this->processSingleColor($default_color, $colorDataMap);

    //         $filteredSelectColor = [];
    //         if (is_array($select_color)) {
    //             foreach ($select_color as $key => $item) {
    //                 $processedColor = $this->processSingleColor($item, $colorDataMap);
    //                 if ($processedColor) {
    //                     $filteredSelectColor[] = $processedColor;
    //                 }
    //             }
    //         }

    //         $param->select_color = $filteredSelectColor;
    //         $param->default_color = $default_color;

    //         $param->map_param = $this->safeUnserialize($param->map_param);
    //         return $param;
    //     });

    //     return $result;
    // }


    private function safeUnserialize($value)
    {
        // 如果已经是数组，直接返回，避免二次反序列化
        if (is_array($value)) {
            return $value;
        }
        if (empty($value)) {
            return [];
        }
        $result = @unserialize($value);
        return is_array($result) ? $result : [];
    }


    private function getColorDataMap($colorIds)
    {
        if (empty($colorIds)) {
            return [];
        }

        return SupplierColorPlane::withTrashed()->select("id", "name", "thumb")
            ->whereIn('id', $colorIds)
            ->get()
            ->keyBy('id')
            ->toArray();
    }


    public function getColorImageUrlSimple($thumb)
    {
        $thumbPath = yz_tomedia($thumb);

        // 如果是完整的OSS URL，提取相对路径部分
        if (strpos($thumbPath, 'https://oss.abangmi.com') === 0) {
            // 从完整URL中提取相对路径
            $relativePath = str_replace('https://oss.abangmi.com/', '', $thumbPath);
        } else {
            // 已经是相对路径，直接使用
            $relativePath = $thumbPath;
        }

        // 移除 thumb 字符串但不改变扩展名
        $filteredPath = preg_replace('/thumb(?=\.\w+$)/', '', $relativePath);

        // 检查不同格式的文件是否存在
        $baseUrl = 'https://abangmi.com/static/upload/';
        $uploadDir = '/static/upload/';

        // 获取文件路径信息 - 注意这里要处理可能带目录的情况
        $pathInfo = pathinfo($filteredPath);

        // 支持的格式
        $formats = ['jpg', 'png'];

        foreach ($formats as $format) {
            $testFile = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.' . $format;

            // 这里需要移除开头的斜杠，因为 base_path 会自动添加
            $serverFile = ltrim($testFile, '/');

            if (file_exists(base_path($uploadDir . $serverFile))) {
                return $baseUrl . $testFile;
            }
        }

        // 没有找到文件，返回原始路径
        return $thumbPath;
    }


    // 辅助方法
    private function processSingleColor($color, $colorDataMap)
    {

        // 检查颜色ID是否存在
        if (!isset($colorDataMap[$color['id']])) {
            return []; // 颜色不存在，返回 null
        }

        if (isset($color['id']) && isset($colorDataMap[$color['id']])) {
            $colorId = $color['id'];
            $color['thumb'] = yz_tomedia($colorDataMap[$colorId]['thumb']);
            $color['high_url'] = $this->getColorImageUrlSimple($colorDataMap[$colorId]['thumb']); //获取原图
            $color['name'] = $colorDataMap[$colorId]['name'];
        }

        // 移除文件扩展名
        if (isset($color['name'])) {
            $color['name'] = preg_replace('/\.(jpg|jpeg|png|gif|bmp|webp|svg|ico)$/i', '', $color['name']);
        }

        return $color;
    }



    public function getAllParentModels($goods_id)
    {
        // 一次性查出所有相关option（兼容多ID拼接）
        $allOptions = GoodsOption::where('goods_id', $goods_id)->get();

        $optionIds = $allOptions->pluck('id')->toArray();

        // 一次性查出所有模型
        $allOptionModels = $this->getOptionModels($optionIds);

        // 构建返回结构
        $parentModels = [];

        $specItemIds = $allOptions->pluck('spec_item_id')->filter()->unique()->toArray();

        // 批量查询GoodsSpecItem数据
        if (!empty($specItemIds)) {
            $specItems = GoodsSpecItem::whereIn('id', $specItemIds)
                ->pluck('productType', 'id')
                ->toArray();
        } else {
            $specItems = [];
        }

        // 按 specs 分组，找出每个组的主要 productType
        $specsGroups = [];
        foreach ($allOptions as $option) {
            $productType = $specItems[$option->spec_item_id] ?? null;
            $specsGroups[$option->specs][$option->id] = [
                'productType' => $productType,
                'option' => $option
            ];
        }

        // 处理每个 specs 组
        foreach ($specsGroups as $specsKey => $options) {
            // 找出该组的主要 productType（取出现次数最多的）
            $productTypeCounts = [];
            foreach ($options as $optionData) {
                $pt = $optionData['productType'];
                $productTypeCounts[$pt] = ($productTypeCounts[$pt] ?? 0) + 1;
            }

            if (empty($productTypeCounts)) {
                continue;
            }

            // 找出出现次数最多的 productType
            arsort($productTypeCounts);
            $mainProductType = array_key_first($productTypeCounts);

            // 根据主要 productType 确定允许的类型
            $allowedTypes = $this->getAllowedModelTypes($mainProductType);

            // 构建该组的模型数据
            $specModelData = [];
            foreach ($options as $optionData) {
                $option = $optionData['option'];
                $productType = $optionData['productType'];
                $modelTypeInfo = $this->getModelType($option->modelType, $productType);
                $modelTypeName = $modelTypeInfo['name'];
                $optionId = $option->id;
                $model_data = $allOptionModels[$optionId] ?? collect();

                // 只包含允许的类型
                if ($productType == 5) {
                    $modelTypeName = "";
                    $specModelData[$modelTypeName] = [
                        "id" => $optionId,
                        "url" => $option->d3ModelUrl_weld ? yz_tomedia($option->d3ModelUrl_weld) : "",
                        "img" => yz_tomedia($option->thumb),
                        "position" => ["x" => 0, "y" => 0, "z" => 0],
                        "rotate" => ["x" => 0, "y" => 0, "z" => 0],
                        "blockname" => $option->block_name,
                        "price" => $option->product_price,
                        "size" => $option->length . "*" . $option->width . "*" . $option->height . "mm",
                        "json" => json_encode([
                            "modelType" => $modelTypeInfo['code'],
                            "single" => $option->singleType,
                            "model_data" => array_values($model_data),
                        ]),
                        'productType' => $productType,
                        "modelType" => $option->modelType,
                        "single" => $option->singleType,
                        "model_data" => array_values($model_data),
                        "length" => $option->length,
                        "width" => $option->width,
                        "height" => $option->height,
                        "d3ModelUrl_weld" => $option->d3ModelUrl_weld ? yz_tomedia($option->d3ModelUrl_weld) : "",
                        "cad_plan_model" => $option->cad_plan_model ? yz_tomedia($option->cad_plan_model) : "",
                        "thumb3dModelUrl" => $option->thumb3dModelUrl ? yz_tomedia($option->thumb3dModelUrl) : "",
                    ];
                } else {
                    if (in_array($modelTypeName, $allowedTypes)) {
                        $specModelData[$modelTypeName] = [
                            "id" => $optionId,

                            "url" => $option->d3ModelUrl_weld ? yz_tomedia($option->d3ModelUrl_weld) : "",
                            "img" => yz_tomedia($option->thumb),
                            "position" => ["x" => 0, "y" => 0, "z" => 0],
                            "rotate" => ["x" => 0, "y" => 0, "z" => 0],
                            "blockname" => $option->block_name,
                            "price" => $option->product_price,
                            "size" => $option->length . "*" . $option->width . "*" . $option->height . "mm",
                            "json" => json_encode([
                                "modelType" => $modelTypeInfo['code'],
                                "single" => $option->singleType,
                                "model_data" => array_values($model_data),
                            ]),
                            'productType' => $productType,
                            "modelType" => $option->modelType,
                            "single" => $option->singleType,
                            "model_data" => array_values($model_data),
                            "length" => $option->length,
                            "width" => $option->width,
                            "height" => $option->height,
                            "d3ModelUrl_weld" => $option->d3ModelUrl_weld ? yz_tomedia($option->d3ModelUrl_weld) : "",
                            "cad_plan_model" => $option->cad_plan_model ? yz_tomedia($option->cad_plan_model) : "",
                            "thumb3dModelUrl" => $option->thumb3dModelUrl ? yz_tomedia($option->thumb3dModelUrl) : "",
                        ];
                    }
                }
            }

            if (!empty($specModelData)) {
                $parentModels[$specsKey] = $specModelData;
            }
        }

        return $parentModels;
    }

    // 新增辅助方法：根据 productType 获取允许的模型类型
    private function getAllowedModelTypes($productType)
    {
        switch ($productType) {
            case 2: // 主辅
                return ['独立位', '延伸位'];
            case 3: // 多元拼接
                return ['独立位', '首位', '延伸位', '尾位'];
            case 4: // 屏风            case 5: // 自由拼接
                return ['十字型', 'T字型', 'L型', 'T字型-1', 'L型-1'];
            default:
                return []; // 其他类型暂时不限制，或者根据需要添加
        }
    }

    private function getModelType($modelType, $productType)
    {
        if ($productType == 1 && $modelType == 0) {
            return ['code' => 0, 'name' => '独立位'];
        } elseif ($productType == 2 && $modelType == 0) {
            return ['code' => 0, 'name' => '独立位'];
        } elseif ($productType == 2 && $modelType == 2) {
            return ['code' => 2, 'name' => '延伸位'];
        } elseif ($productType == 3 && $modelType == 0) {
            return ['code' => 0, 'name' => '独立位'];
        } elseif ($productType == 3 && $modelType == 1) {
            return ['code' => 1, 'name' => '首位'];
        } elseif ($productType == 3 && $modelType == 2) {
            return ['code' => 2, 'name' => '延伸位'];
        } elseif ($productType == 3 && $modelType == 3) {
            return ['code' => 3, 'name' => '尾位'];
        } elseif ($productType == 4 && $modelType == 0) {
            return ['code' => 0, 'status' => 8, 'name' => '十字型'];
        } elseif ($productType == 4 && $modelType == 1) {
            return ['code' => 1, 'status' => 8, 'name' => 'T字型'];
        } elseif ($productType == 4 && $modelType == 2) {
            return ['code' => 2, 'status' => 8, 'name' => 'L型'];
        } elseif ($productType == 4 && $modelType == 3) {
            return ['code' => 3, 'status' => 8, 'name' => 'T字型-1'];
        } elseif ($productType == 4 && $modelType == 4) {
            return ['code' => 4, 'status' => 8, 'name' => 'L型-1'];
        }
    }



    public function getAllParentDiyModels($goods_id)
    {
        // 一次性查出所有相关option（兼容多ID拼接）
        $allOptions = GoodsOption::where('goods_id', $goods_id)
            ->with(['beLongsToSupplier' => function ($query) {
                $query->select("id", "store_name");
            }])
            ->get();

        $optionIds = $allOptions->pluck('id')->toArray();

        // 一次性查出所有模型
        $allOptionModels = $this->getOptionModels($optionIds);

        // 构建返回结构
        $parentModels = [];

        $specItemIds = $allOptions->pluck('spec_item_id')->filter()->unique()->toArray();

        // 批量查询GoodsSpecItem数据
        if (!empty($specItemIds)) {
            $specItems = GoodsSpecItem::whereIn('id', $specItemIds)
                ->pluck('productType', 'id')
                ->toArray();
        } else {
            $specItems = [];
        }

        // 按 specs 分组处理
        $specsGroups = [];
        foreach ($allOptions as $option) {
            $specsGroups[$option->specs][] = $option;
        }

        // 处理每个 specs 组
        foreach ($specsGroups as $specsKey => $options) {
            $specModelData = [];

            foreach ($options as $option) {
                $productType = $specItems[$option->spec_item_id] ?? null;
                $optionId = $option->id;
                $model_data = $allOptionModels[$optionId] ?? collect();

                // 构建选项数据，保持原有格式
                $specModelData[] = [
                    "id" => $optionId,
                    "goods_id" => $option->goods_id,
                    "d3ModelUrl_weld" => $option->d3ModelUrl_weld ? yz_tomedia($option->d3ModelUrl_weld) : "",
                    "cad_plan_model" => $option->cad_plan_model ? yz_tomedia($option->cad_plan_model) : "",
                    "thumb3dModelUrl" => $option->thumb3dModelUrl ? yz_tomedia($option->thumb3dModelUrl) : "",
                    "thumb" => yz_tomedia($option->thumb),
                    "blockname" => $option->block_name,
                    "price" => $option->product_price,
                    'productType' => $productType,
                    "modelType" => $option->modelType,
                    "single" => $option->singleType,
                    "model_data" => array_values($model_data),
                    "length" => $option->length,
                    "width" => $option->width,
                    "height" => $option->height,
                    "be_longs_to_supplier" => $option->beLongsToSupplier,
                ];
            }

            if (!empty($specModelData)) {
                $parentModels[$specsKey] = $specModelData;
            }
        }

        return $parentModels;
    }


    public function buildDocument($option_id)
    {
        // 使用 Eloquent 模型查询，预加载关联关系
        $item = GoodsOption::with([
            'goods' => function ($query) {
                $query->select('id', 'title', 'keywords', 'is_discount', 'is_hot', 'supp_id', 'sku', 'is_stock', 'lead_time', 'status', 'created_at'); //->with(['hasManyOptions.hasManyOptionModels']);
            },
            'beLongsToSupplier' => function ($query) {
                $query->select('id', 'store_name', 'city_id', 'bid_enable', 'enable');
            },
            // 'hasManyOptionModels' => function($query) {
            //     $query->select('id', 'option_id', 'name', 'originName', 'changeLock', 'default_color', 'select_color', 'map_param', 'meshs_name', 'sort', 'visible');
            // },
            'goods.hasManyGoodsCategory' => function ($query) {
                $query->with(['category' => function ($q) {
                    $q->select('id', 'name', 'parent_id');
                }]);
            },
            'goods.hasManyStyleRelations' => function ($query) {
                $query->with(['belongsToStyle' => function ($q) {
                    $q->select('id', 'name');
                }]);
            }
        ])
            ->select('id', 'goods_id', 'title', 'product_price', 'is_discount', 'is_hot', 'show_sales', 'comment_num', 'stock', 'thumb', 'length', 'supp_id', 'cad_plan_model', 'd3MaxUrl', 'd3ModelUrl_weld', 'd3ModelUrl_ori', 'thumb3dModelUrl', 'width', 'height', 'mirror_enable')
            ->find($option_id);

        if (!$item) return null;

        // 处理分类数据
        $category_ids = [];
        $category_names = [];

        if ($item->goods && $item->goods->hasManyGoodsCategory) {
            foreach ($item->goods->hasManyGoodsCategory as $goodsCategory) {
                if ($goodsCategory->category) {
                    $category_ids[] = $goodsCategory->category->id;
                    $category_names[] = $goodsCategory->category->name;

                    // 如果有父级分类，也添加进去
                    if ($goodsCategory->category->parent_id) {
                        $parentCategory = Category::find($goodsCategory->category->parent_id);
                        if ($parentCategory) {
                            $category_ids[] = $parentCategory->id;
                            $category_names[] = $parentCategory->name;
                        }
                    }
                }
            }
        }

        // 去重
        $category_ids = array_unique($category_ids);
        $category_names = array_unique($category_names);
        $category_names = implode('|', $category_names);

        // 处理材质/风格数据
        $materialArr = ['material_id' => [], 'material' => []];
        $styleArr = ['style_id' => [], 'style' => []];

        if ($item->goods && $item->goods->hasManyStyleRelations) {
            foreach ($item->goods->hasManyStyleRelations as $styleRelation) {
                if ($styleRelation->belongsToStyle) {
                    if ($styleRelation->type == 2) {
                        $materialArr['material_id'][] = $styleRelation->style_id;
                        $materialArr['material'][] = $styleRelation->belongsToStyle->name;
                    } elseif ($styleRelation->type == 1) {
                        $styleArr['style_id'][] = $styleRelation->style_id;
                        $styleArr['style'][] = $styleRelation->belongsToStyle->name;
                    }
                }
            }
        }

        $material_names = implode("|", $materialArr['material']);
        $style_names = implode("|", $styleArr['style']);



        return [
            'id' => (int)$item->id,
            'option_id' => (int)$item->id,
            'goods_id' => (int)$item->goods_id,
            'option_title' => $item->title,
            'price' => (float)$item->product_price,
            'show_sales' => (int)$item->show_sales,
            'comment_num' => (int)$item->comment_num,
            'is_stock' => (int)($item->goods ? $item->goods->is_stock : 0),
            'length' => (float)$item->length,
            'width' => (float)$item->width,
            'height' => (float)$item->height,
            'mirror_enable' => (int)$item->mirror_enable,
            'cad_plan_model' => yz_tomedia($item->cad_plan_model),
            'd3MaxUrl' => yz_tomedia($item->d3MaxUrl),
            'd3ModelUrl_weld' => yz_tomedia($item->d3ModelUrl_weld),
            'd3ModelUrl_ori' => yz_tomedia($item->d3ModelUrl_ori),
            'thumb3dModelUrl' => $item->thumb3dModelUrl ? yz_tomedia($item->thumb3dModelUrl) : null,
            'stock' => (int)$item->stock,
            'goods_title' => $item->goods ? $item->goods->title : '',
            // 'goods_keywords' => $item->goods ? $item->goods->keywords : '',
            'category_id' => $category_ids,
            'category_name' => $category_names,
            'supplier_name' => $item->beLongsToSupplier ? $item->beLongsToSupplier->store_name : '',
            'supplier_id' => $item->goods ? $item->goods->supp_id : 0,
            'supplier_city_id' => $item->beLongsToSupplier ? $item->beLongsToSupplier->city_id : 0,
            'bid_enable' => $item->beLongsToSupplier ? $item->beLongsToSupplier->bid_enable : 0,
            'lead_time' => $item->goods ? $item->goods->lead_time : '',
            'material_id' => $materialArr['material_id'],
            'material_name' => $material_names,
            'sku' => $item->goods->sku,
            'style_id' => $styleArr['style_id'],
            'style_name' => $style_names,
            'created_at' => (int)($item->goods ? $item->goods->created_at : 0),
            'thumb' => yz_tomedia($item->thumb),
            'is_discount' => (int)$item->is_discount,
            'is_hot' => (int)$item->is_hot,
            'be_longs_to_supplier' => [
                'id' => $item->beLongsToSupplier->id,
                'store_name' => $item->beLongsToSupplier->store_name,
                'supplier_id' => $item->beLongsToSupplier->id,
            ],
            'status' => (int)($item->goods ? $item->goods->status : 0),
            'enable' => (int)($item->beLongsToSupplier ? $item->beLongsToSupplier->enable : 0),
            //'option_models' => $optionModels,
            'display_order' => (int)$item->goods->display_order,
            // 规范化 goods 的 hasManyOptions/hasManyOptionModels 输出
            //'goods' => $this->normalizeGoods($item->goods),
        ];
    }



    /**
     * 批量构建文档
     */
    public function buildBatchDocuments(array $optionIds)
    {
        // 批量预加载所有数据
        $items = GoodsOption::with([
            'goods' => function ($query) {
                $query->select(
                    'id',
                    'title',
                    'keywords',
                    'is_discount',
                    'is_hot',
                    'supp_id',
                    'sku',
                    'is_stock',
                    'lead_time',
                    'status',
                    'created_at',
                    'display_order'
                );
            },
            'beLongsToSupplier' => function ($query) {
                $query->select('id', 'store_name', 'province_id', 'city_id', 'bid_enable', 'enable');
            },
            'specCategory' => function ($query) {
                // 直接加载分类信息，同时获取父级分类
                $query->with(['category' => function ($q) {
                    $q->select('id', 'name', 'display_order', 'level');
                }]);
            },
            'goods.hasManyStyleRelations' => function ($query) {
                $query->with(['belongsToStyle' => function ($q) {
                    $q->select('id', 'name');
                }]);
            }
        ])
            ->select(
                'id',
                'goods_id',
                'title',
                'product_price',
                'is_discount',
                'is_hot',
                'show_sales',
                'comment_num',
                'stock',
                'thumb',
                'length',
                'supp_id',
                'cad_plan_model',
                'd3MaxUrl',
                'd3ModelUrl_weld',
                'd3ModelUrl_ori',
                'thumb3dModelUrl',
                'width',
                'height',
                'mirror_enable',
                'spec_item_id',
                'order_sort'
            )
            ->whereIn('id', $optionIds)
            ->get();

        if ($items->isEmpty()) {
            return [];
        }

        // 需要单独查询父分类信息（因为specCategory表只存储了parent_category_id，需要获取对应的分类名称）
        $parentCategoryIds = [];
        foreach ($items as $item) {
            if ($item->specCategory) {
                foreach ($item->specCategory as $specCategory) {
                    // 如果category_type=2（二级分类），则需要查询父分类
                    if ($specCategory->category_type == 2 && $specCategory->parent_category_id > 0) {
                        $parentCategoryIds[] = $specCategory->parent_category_id;
                    }
                }
            }
        }

        // 批量查询父分类
        $parentCategories = [];
        if (!empty($parentCategoryIds)) {
            $parentCategories = Category::whereIn('id', array_unique($parentCategoryIds))
                ->select('id', 'name', 'display_order')
                ->get()
                ->keyBy('id');
        }

        $docs = [];
        foreach ($items as $item) {
            $doc = $this->buildSingleDocument($item, $parentCategories);
            if ($doc) {
                $docs[] = $doc;
            }
        }

        return $docs;
    }



    /**
     * 构建单个文档
     */
    private function buildSingleDocument($item, $parentCategories = [])
    {
        if (!$item || !$item->goods) {
            return null;
        }

        // 处理分类数据（从 specCategory 关联获取）
        $category_ids = [];
        $category_names = [];

        // 获取分类排序信息
        $category_sorts = []; // 原来的GoodsCategorySort.category_sort
        $category_final_sorts = []; // CategoryGoodsSort.final_sort - 新的排序值
        $category_sales_ranks = [];
        $category_line_nums = [];

        // 分别存储一级分类和二级分类的display_order
        $category_last_display_order_primary = [];   // 一级分类的display_order
        $category_last_display_order_secondary = []; // 二级分类的display_order

        if ($item->specCategory) {
            foreach ($item->specCategory as $specCategory) {
                if ($specCategory->category) {
                    $categoryId = $specCategory->category_id;

                    // 1. 首先查询新的分类排序数据（从CategoryGoodsSort表）
                    $categoryGoodsSort = CategoryGoodsSort::where('option_id', $item->id)
                        ->where('category_sort_id', $categoryId) // category_sort_id 对应分类ID
                        ->first();

                    if ($categoryGoodsSort) {
                        // 使用final_sort作为排序值
                        $category_final_sorts[$categoryId] = (int)$categoryGoodsSort->final_sort;
                    }

                    // 2. 查询原来的分类排序信息（如果需要保留）
                    $goodsCategorySort = GoodsCategorySort::where('sub_category_id', $categoryId)->first();
                    if ($goodsCategorySort) {
                        $category_sorts[$categoryId] = $goodsCategorySort->category_sort;
                        $category_sales_ranks[$categoryId] = $goodsCategorySort->category_sales_rank;
                        $category_line_nums[$categoryId] = $goodsCategorySort->line_num;
                    }

                    // 添加当前分类
                    $category_ids[] = $specCategory->category->id;
                    $category_names[] = $specCategory->category->name;

                    // 根据分类类型分别存储display_order
                    if ($specCategory->category_type == 1) {
                        // 一级分类
                        $category_last_display_order_primary[$categoryId] = $specCategory->category->display_order;
                    } else {
                        // 二级分类
                        $category_last_display_order_secondary[$categoryId] = $specCategory->category->display_order;
                    }

                    // 如果是二级分类，添加对应的父级分类
                    if ($specCategory->category_type == 2 && $specCategory->parent_category_id > 0) {
                        if (isset($parentCategories[$specCategory->parent_category_id])) {
                            $parentCategory = $parentCategories[$specCategory->parent_category_id];
                            $category_ids[] = $parentCategory->id;
                            $category_names[] = $parentCategory->name;
                            // 父级分类是一级分类，根据需要可加入一级分类display_order，此处暂不处理以保持原逻辑
                        }
                    }
                }
            }
        }

        // ================= 主分类确定逻辑（优化版） =================
        $best_category_id = 0;
        $best_category_final_sort = PHP_INT_MAX;

        // 收集商品所有涉及的分类ID（用于查询配置）
        $allCategoryIds = array_unique($category_ids);

        if (!empty($allCategoryIds)) {
            // 查询这些分类在 GoodsCategorySort 表中的配置，按 category_sort 升序
            $configuredSorts = GoodsCategorySort::whereIn('sub_category_id', $allCategoryIds)
                ->orderBy('category_sort', 'asc')
                ->get()
                ->keyBy('sub_category_id');

            if ($configuredSorts->isNotEmpty()) {
                // 存在已配置的分类，选取 category_sort 最小的一个
                $firstConfigured = $configuredSorts->first();
                $best_category_id = $firstConfigured->sub_category_id;
                $best_category_final_sort = $category_final_sorts[$best_category_id] ?? PHP_INT_MAX;
            } else {
                // 都不在配置中，沿用原逻辑：取 final_sort 最小的分类
                if (!empty($category_final_sorts)) {
                    $best_category_final_sort = min($category_final_sorts);
                    $best_category_id = array_search($best_category_final_sort, $category_final_sorts);
                } else {
                    // 连 final_sort 都没有，则取第一个分类ID
                    $best_category_id = $category_ids[0] ?? 0;
                }
            }
        }

        // 兜底：如果仍为0且商品有分类，取第一个分类ID
        if ($best_category_id == 0 && !empty($category_ids)) {
            $best_category_id = $category_ids[0];
        }

        // ================= 其他排序值计算（保持原有逻辑） =================
        $best_category_last_sort = !empty($category_last_display_order_primary)
            ? min(array_values($category_last_display_order_primary))
            : PHP_INT_MAX;

        $best_category_two_last_sort = !empty($category_last_display_order_secondary)
            ? min(array_values($category_last_display_order_secondary))
            : PHP_INT_MAX;

        $category_last_display_order = array_merge($category_last_display_order_primary, $category_last_display_order_secondary);

        $best_category_sort = empty($category_sorts) ? PHP_INT_MAX : min(array_values($category_sorts));

        // 去重
        $category_ids = array_unique($category_ids);
        $category_names = array_unique($category_names);
        $category_names = implode('|', $category_names);
        $category_ids = array_values($category_ids);

        // 处理材质/风格数据（保持原逻辑）
        $materialArr = ['material_id' => [], 'material' => []];
        $styleArr = ['style_id' => [], 'style' => []];

        if ($item->goods && $item->goods->hasManyStyleRelations) {
            foreach ($item->goods->hasManyStyleRelations as $styleRelation) {
                if ($styleRelation->belongsToStyle) {
                    if ($styleRelation->type == 2) {
                        $materialArr['material_id'][] = $styleRelation->style_id;
                        $materialArr['material'][] = $styleRelation->belongsToStyle->name;
                    } elseif ($styleRelation->type == 1) {
                        $styleArr['style_id'][] = $styleRelation->style_id;
                        $styleArr['style'][] = $styleRelation->belongsToStyle->name;
                    }
                }
            }
        }

        $material_names = implode("|", $materialArr['material']);
        $style_names = implode("|", $styleArr['style']);

        $doc = [
            'id' => (int)$item->id,
            'option_id' => (int)$item->id,
            'goods_id' => (int)$item->goods_id,
            'option_title' => $item->title,
            'price' => (float)$item->product_price,
            'show_sales' => (int)$item->show_sales,
            'comment_num' => (int)$item->comment_num,
            'is_stock' => (int)($item->goods->is_stock ?? 0),
            'length' => (float)$item->length,
            'width' => (float)$item->width,
            'height' => (float)$item->height,
            'mirror_enable' => (int)$item->mirror_enable,
            'cad_plan_model' => yz_tomedia($item->cad_plan_model),
            'd3MaxUrl' => yz_tomedia($item->d3MaxUrl),
            'd3ModelUrl_weld' => yz_tomedia($item->d3ModelUrl_weld),
            'd3ModelUrl_ori' => yz_tomedia($item->d3ModelUrl_ori),
            'thumb3dModelUrl' => $item->thumb3dModelUrl ? yz_tomedia($item->thumb3dModelUrl) : null,
            'stock' => (int)$item->stock,
            'goods_title' => $item->goods->title ?? '',
            'title' => $item->goods->title.explode("+",$item->title)[0].$category_names.$style_names,
            'category_id' => $category_ids,
            'category_name' => $category_names,
            'supplier_name' => $item->beLongsToSupplier->store_name ?? '',
            'supplier_id' => $item->goods->supp_id ?? 0,
            'supplier_city_id' => (int)$item->beLongsToSupplier->province_id ?? 0,
            'bid_enable' => $item->beLongsToSupplier->bid_enable ?? 0,
            'lead_time' => $item->goods->lead_time ?? '',
            'material_id' => $materialArr['material_id'],
            'material_name' => $material_names,
            'sku' => $item->goods->sku ?? '',
            'style_id' => $styleArr['style_id'],
            'style_name' => $style_names,
            'created_at' => (int)($item->goods->created_at ?? 0),
            'thumb' => yz_tomedia($item->thumb),
            'is_discount' => (int)$item->is_discount,
            'is_hot' => (int)$item->is_hot,
            'be_longs_to_supplier' => [
                'id' => $item->beLongsToSupplier->id ?? null,
                'store_name' => $item->beLongsToSupplier->store_name ?? '',
                'supplier_id' => $item->beLongsToSupplier->id ?? null,
            ],
            'status' => (int)($item->goods->status ?? 0),
            'enable' => (int)($item->beLongsToSupplier->enable ?? 0),
            'display_order' => (int)($item->goods->display_order ?? 0),
            'category_sort' => $category_sorts,
            'category_sales_rank' => $category_sales_ranks,
            'category_line_num' => $category_line_nums,
            'category_final_sort' => $category_final_sorts,
            'option_order_sort' => (int)($item->order_sort ?? 0),
            'best_category_sort' => (int)$best_category_sort,
            'best_category_final_sort' => (int)$best_category_final_sort,
            'best_category_id' => (int)$best_category_id,
            'category_last_display_order' => $category_last_display_order,
            'best_category_last_sort' => (int)$best_category_last_sort,
            'best_category_two_last_sort' => (int)$best_category_two_last_sort,
        ];

        // 扁平化分类排序字段，便于 Meilisearch 高效排序
        foreach ($category_final_sorts as $catId => $sortValue) {
            $doc["sort_cat_{$catId}"] = (int)$sortValue;
        }

        return $doc;
    }


    /**
     * 构建单个文档
     */
    // private function buildSingleDocument($item, $parentCategories = [])
    // {
    //     if (!$item || !$item->goods) {
    //         return null;
    //     }

    //     // 处理分类数据（从 specCategory 关联获取）
    //     $category_ids = [];
    //     $category_names = [];

    //     // 获取分类排序信息
    //     $category_sorts = []; // 原来的GoodsCategorySort.category_sort
    //     $category_final_sorts = []; // CategoryGoodsSort.final_sort - 新的排序值
    //     $category_sales_ranks = [];
    //     $category_line_nums = [];
    //     $category_last_display_order = [];

    //     if ($item->specCategory) {
    //         foreach ($item->specCategory as $specCategory) {
    //             if ($specCategory->category) {
    //                 $categoryId = $specCategory->category_id;

    //                 // 1. 首先查询新的分类排序数据（从CategoryGoodsSort表）
    //                 $categoryGoodsSort = CategoryGoodsSort::where('option_id', $item->id)
    //                     ->where('category_sort_id', $categoryId) // category_sort_id 对应分类ID
    //                     ->first();

    //                 if ($categoryGoodsSort) {
    //                     // 使用final_sort作为排序值
    //                     $category_final_sorts[$categoryId] = (int)$categoryGoodsSort->final_sort;
    //                 } else {
    //                     // 如果没有排序记录，使用一个较大的默认值
    //                     $category_final_sorts[$categoryId] = 999999;
    //                 }

    //                 // 2. 查询原来的分类排序信息（如果需要保留）
    //                 $goodsCategorySort = GoodsCategorySort::where('sub_category_id', $categoryId)->first();
    //                 if ($goodsCategorySort) {
    //                     $category_sorts[$categoryId] = $goodsCategorySort->category_sort;
    //                     $category_sales_ranks[$categoryId] = $goodsCategorySort->category_sales_rank;
    //                     $category_line_nums[$categoryId] = $goodsCategorySort->line_num;
    //                 }

    //                 // 添加当前分类
    //                 $category_ids[] = $specCategory->category->id;
    //                 $category_names[] = $specCategory->category->name;
    //                 $category_last_display_order[] = $specCategory->category->display_order;

    //                 // 如果是二级分类，添加对应的父级分类
    //                 if ($specCategory->category_type == 2 && $specCategory->parent_category_id > 0) {
    //                     if (isset($parentCategories[$specCategory->parent_category_id])) {
    //                         $parentCategory = $parentCategories[$specCategory->parent_category_id];
    //                         $category_ids[] = $parentCategory->id;
    //                         $category_names[] = $parentCategory->name;
    //                     }
    //                 }
    //             }
    //         }
    //     }

    //     // 计算最佳分类排序（基于新的category_final_sorts）
    //     $best_category_final_sort = empty($category_final_sorts) ? PHP_INT_MAX : min(array_values($category_final_sorts));

    //     $best_category_last_sort = empty($category_last_display_order) ? PHP_INT_MAX : min(array_values($category_last_display_order));

    //     // 找到最佳排序对应的分类ID
    //     $best_category_id = 0;
    //     foreach ($category_final_sorts as $categoryId => $sortValue) {
    //         if ($sortValue == $best_category_final_sort) {
    //             $best_category_id = $categoryId;
    //             break;
    //         }
    //     }

    //     // 如果还需要保留原来的最佳排序逻辑
    //     $best_category_sort = empty($category_sorts) ? PHP_INT_MAX : min(array_values($category_sorts));

    //     // 去重
    //     $category_ids = array_unique($category_ids);
    //     $category_names = array_unique($category_names);
    //     $category_names = implode('|', $category_names);
    //     $category_ids = array_values($category_ids);

    //     // 处理材质/风格数据
    //     $materialArr = ['material_id' => [], 'material' => []];
    //     $styleArr = ['style_id' => [], 'style' => []];

    //     if ($item->goods && $item->goods->hasManyStyleRelations) {
    //         foreach ($item->goods->hasManyStyleRelations as $styleRelation) {
    //             if ($styleRelation->belongsToStyle) {
    //                 if ($styleRelation->type == 2) {
    //                     $materialArr['material_id'][] = $styleRelation->style_id;
    //                     $materialArr['material'][] = $styleRelation->belongsToStyle->name;
    //                 } elseif ($styleRelation->type == 1) {
    //                     $styleArr['style_id'][] = $styleRelation->style_id;
    //                     $styleArr['style'][] = $styleRelation->belongsToStyle->name;
    //                 }
    //             }
    //         }
    //     }

    //     $material_names = implode("|", $materialArr['material']);
    //     $style_names = implode("|", $styleArr['style']);

    //     return [
    //         'id' => (int)$item->id,
    //         'option_id' => (int)$item->id,
    //         'goods_id' => (int)$item->goods_id,
    //         'option_title' => $item->title,
    //         'price' => (float)$item->product_price,
    //         'show_sales' => (int)$item->show_sales,
    //         'comment_num' => (int)$item->comment_num,
    //         'is_stock' => (int)($item->goods->is_stock ?? 0),
    //         'length' => (float)$item->length,
    //         'width' => (float)$item->width,
    //         'height' => (float)$item->height,
    //         'mirror_enable' => (int)$item->mirror_enable,
    //         'cad_plan_model' => yz_tomedia($item->cad_plan_model),
    //         'd3MaxUrl' => yz_tomedia($item->d3MaxUrl),
    //         'd3ModelUrl_weld' => yz_tomedia($item->d3ModelUrl_weld),
    //         'd3ModelUrl_ori' => yz_tomedia($item->d3ModelUrl_ori),
    //         'thumb3dModelUrl' => $item->thumb3dModelUrl ? yz_tomedia($item->thumb3dModelUrl) : null,
    //         'stock' => (int)$item->stock,
    //         'goods_title' => $item->goods->title ?? '',
    //         'goods_keywords' => $item->goods->keywords ?? '',
    //         'category_id' => $category_ids,
    //         'category_name' => $category_names,
    //         'supplier_name' => $item->beLongsToSupplier->store_name ?? '',
    //         'supplier_id' => $item->goods->supp_id ?? 0,
    //         'supplier_city_id' => $item->beLongsToSupplier->city_id ?? 0,
    //         'bid_enable' => $item->beLongsToSupplier->bid_enable ?? 0,
    //         'lead_time' => $item->goods->lead_time ?? '',
    //         'material_id' => $materialArr['material_id'],
    //         'material_name' => $material_names,
    //         'sku' => $item->goods->sku ?? '',
    //         'style_id' => $styleArr['style_id'],
    //         'style_name' => $style_names,
    //         'created_at' => (int)($item->goods->created_at ?? 0),
    //         'thumb' => yz_tomedia($item->thumb),
    //         'is_discount' => (int)$item->is_discount,
    //         'is_hot' => (int)$item->is_hot,
    //         'be_longs_to_supplier' => [
    //             'id' => $item->beLongsToSupplier->id ?? null,
    //             'store_name' => $item->beLongsToSupplier->store_name ?? '',
    //             'supplier_id' => $item->beLongsToSupplier->id ?? null,
    //         ],
    //         'status' => (int)($item->goods->status ?? 0),
    //         'enable' => (int)($item->beLongsToSupplier->enable ?? 0),
    //         'display_order' => (int)($item->goods->display_order ?? 0),
    //         'category_sort' => $category_sorts, // 原来的GoodsCategorySort.category_sort
    //         'category_sales_rank' => $category_sales_ranks,
    //         'category_line_num' => $category_line_nums,
    //         'category_final_sort' => $category_final_sorts, // 新的CategoryGoodsSort.final_sort
    //         'option_order_sort' => (int)($item->order_sort ?? 0),
    //         'best_category_sort' => (int)$best_category_sort, // 基于原来的最佳排序
    //         'best_category_final_sort' => (int)$best_category_final_sort, // 基于新的最佳排序
    //         'best_category_id' => (int)$best_category_id, // 最佳排序对应的分类ID
    //         'best_category_last_sort' => (int)$best_category_last_sort, //基于普通分类的最佳排序
    //     ];
    // }


    public function getMirrorType($optionId, $is_mirrored)
    {
        $goodsOption = GoodsOption::find($optionId);
        $mirrorType = 0;
        if ($goodsOption->mirror_enable > 0 && $is_mirrored) {
            switch ($goodsOption->mirror_enable) {
                case 1:
                    $mirrorType = 2;
                    break;
                case 2:
                    $mirrorType = 1;
                    break;
            }
        } elseif ($goodsOption->mirror_enable > 0 && $is_mirrored == 0) {
            $mirrorType = $goodsOption->mirror_enable;
        }
        return $mirrorType;
    }
}
