<?php


namespace app\common\services\goods;

use app\common\models\goods\GoodsStyle;
use app\common\models\Category;
use app\common\services\goods\CreateGoodsService;
use Illuminate\Support\Facades\Redis;
use Yunshop\Supplier\common\models\Supplier;

class ImportGoodsService
{
    private $uniacid;

    private $relativeFolder;


    private $productType;


    const INSTALLATION_GUIDE = '安装指南';
    const MAINTENANCE_MANUAL = '保养手册';
    const PRODUCT_PHOTOS = '商品实拍图';
    const SPECIFICATIONS = '规格';
    const PRODUCT_VIDEO = '商品视频';
    const PRODUCT_CATALOG = '商品图册';
    const PRODUCT_RENDERINGS = '商品效果图';
    const MAIN_PRODUCT_IMAGE = '商品主图';
    const WIRING_DIAGRAM = '其他';

    public function init($excelData, $relativeDir, $uniacid,$taskId,$supplierId)
    {


        try {
            $res = Supplier::find(35);

        }catch (\Exception $e){
            print_r($e->getMessage());die;
        }

        $this->uniacid = $uniacid;

        $mainGoodsData = $this->combineExcelData($excelData);




        $result = [];
        // 初始化进度条和失败记录
        $totalCount = count($mainGoodsData);
        $successCount = 0;
        $failCount = 0;
        $failedGoods = [];

        if ($taskId) {
            $this->updateTaskProgress($taskId, 1, $totalCount, 0, 0, []);
        }

        // 预先收集所有需要查询的名称
        $allCategoryNames = [];
        $allCraftMaterialNames = [];
        $allGoodsStyleNames = [];

        // 添加调试代码检查数据结构
        foreach ($mainGoodsData as $index => $goodsDatum) {

          
            // 确保合并的是数组
            $allCategoryNames = array_merge(
                $allCategoryNames,
                ...array_filter([$goodsDatum['cate_name'] ?? [], $goodsDatum['cate_parent_name'] ?? []])
            );

            $allCraftMaterialNames = array_merge(
                $allCraftMaterialNames,
                (array)($goodsDatum['process_material'] ?? [])
            );

            $allGoodsStyleNames = array_merge(
                $allGoodsStyleNames,
                (array)($goodsDatum['goods_style'] ?? [])
            );
        }



        // 批量查询

        $categoryMap = Category::whereIn('name', array_unique($allCategoryNames))
            ->get()
            ->keyBy('name')
            ->toArray();



        $craftMaterialMap = GoodsStyle::whereIn('name', array_unique($allCraftMaterialNames))
            ->get()
            ->keyBy('name')
            ->toArray();

        $goodsStyleMap = GoodsStyle::whereIn('name', array_unique($allGoodsStyleNames))
            ->get()
            ->keyBy('name')
            ->toArray();



        // 处理每个商品
        foreach ($mainGoodsData as $key => $goodsDatum) {

             $res = \app\common\models\Goods::where('title', $goodsDatum['goods_title'])->where('supp_id',$supplierId)->first();
                if($res){
                \Log::error($goodsDatum['goods_title']."已导入");
                continue;
                }
            try {
                $currentIndex = $key + 1;
                $goodsTitle = $goodsDatum['goods_title'] ?? '未知商品';
                $relativeFolder = $relativeDir . "/" . $goodsDatum['goods_title'] . "/";

                $this->relativeFolder = $relativeFolder;





                $categoryData = $this->processCategoryDataBatch(
                    $goodsDatum['cate_name'] ?? [],
                    $goodsDatum['cate_parent_name'] ?? [],
                    $categoryMap
                );


                $craftMaterials = $this->processNamesToIdsBatch(
                    $goodsDatum['process_material'] ?? [],
                    $craftMaterialMap
                );

                $goodsStyles = $this->processNamesToIdsBatch(
                    $goodsDatum['goods_style'] ?? [],
                    $goodsStyleMap
                );



                if (!empty($goodsDatum['goods_option'])) {

                    $goodsOptionData = $this->processGoodsOption($goodsDatum['goods_option'], $goodsDatum['spec_product'],$goodsDatum);


                }

                $ECatalogPdfData = $this->processECatalogPdf($relativeFolder);



                //商品主图
              //  $goods_main_data = $this->processMainImage($relativeFolder,'goodsmain');
                //效果图
                $goods_thumb_url = $this->processImage($relativeFolder.self::PRODUCT_RENDERINGS, 'goodsmain');

                //真实图
                $goods_real_image = $this->processImage($relativeFolder.self::PRODUCT_PHOTOS, 'goodsmain');

                //走线示意图
                $goods_wiring_diagram = $this->processImage($relativeFolder.self::WIRING_DIAGRAM, 'goodsmain');

                //处理商品视频

                $goods_video = $this->processVideo($relativeFolder.self::PRODUCT_VIDEO);
                // //如果安装指南有数据

                // $install_guide_data = $this->processInstallGuide($relativeFolder.self::INSTALLATION_GUIDE);

                //如果保养手册有数据

                $maintenance_doc_data = $this->processInstallGuide($relativeFolder.self::MAINTENANCE_MANUAL);




                $result = [
                    //'goods_data' => $goodsDatum,
                    'keywords'=>$goodsDatum['keywords']?:"",
                    'category' => $categoryData,
                    'craft_materials' => $craftMaterials,
                    'goods_style' => $goodsStyles,
                    'other_name' => "其他",
                    'title' => $goodsDatum['goods_title'],
                    'sku' => $goodsDatum['sku'],
                    'stock' => $goodsDatum['stock'],
                    'is_stock' => $goodsDatum['is_stock'],
                    'lead_time' => $goodsDatum['lead_time'],
                    'warranty' => $goodsDatum['warranty'],
                    //'productType' => $productType,
                    'atlas' => $ECatalogPdfData['atlas'],
                    'e_catalog_pdf' => $ECatalogPdfData['e_catalog_pdf'],
                    'pdf_page_img' => $ECatalogPdfData['pdf_page_img'],
                    'thumb' => "",
                    'main_url' => [],
                    'plugin_id'=>92,
                    'thumb_url' => $goods_thumb_url,
                    'supplier_id'=>$supplierId,
                    'uniacid'=>$uniacid,
                    'real_image' => $goods_real_image,
                    'wiring_diagram' => $goods_wiring_diagram,
                    'goods_video' => $goods_video,
                   // 'install_guide_url' => !empty($install_guide_data) ? $install_guide_data['pdf_file'] : "",
                  //  'install_guide' => !empty($install_guide_data) ? $install_guide_data['data'] : [],
                    'maintenance_doc_url' => !empty($maintenance_doc_data) ? $maintenance_doc_data['pdf_file'] : "",
                    'maintenance_doc' => !empty($maintenance_doc_data) ? $maintenance_doc_data['data'] : [],
                    'goods_option' => $goodsOptionData

                ];

                $request = Request();
                $createGoodsService = new CreateGoodsService($request,1);
                $res = $createGoodsService->importGoods($result);
                \Log::error("=====导入结果====",$res);
                // 导入成功
                $successCount++;
            } catch (\Exception $e) {
                // 导入失败，记录错误信息
                $failCount++;
                $failedGoods[] = [
                    'goods_title' => $goodsTitle ?? '未知商品',
                    //  'sku' => $goodsDatum['sku'] ?? '未知SKU',
                    // 'error' => $e->getMessage(),
//                  'line' => $e->getLine(),
//                  'file' => $e->getFile()
                ];

                // 记录详细日志
                \Log::error("商品导入失败: " . ($goodsTitle ?? '未知') . " - " . $e->getMessage());
            }

            if ($taskId) {
                $progress = round(($currentIndex / $totalCount) * 100, 2);
                $this->updateTaskProgress($taskId, $progress, $totalCount, $successCount, $failCount, $failedGoods);
            }
        }
        // 最终更新进度条
        if ($taskId) {
            $this->updateTaskProgress($taskId, 100, $totalCount, $successCount, $failCount, $failedGoods);
        }


        return true;
    }



    /**
     * 更新任务进度到Redis
     */
    private function updateTaskProgress($taskId, $progress, $total, $success, $failed, $failedGoods)
    {
        try {
            $progressData = [
                'task_id' => $taskId,
                'progress' => $progress,
                'total' => $total,
                'success' => $success,
                'failed' => $failed,
                'failed_goods' => $failedGoods,
                'update_time' => now()->toDateTimeString(),
                'status' => $progress >= 100 ? 1 : 0
            ];

            // 使用 Redis 门面，设置24小时过期
            Redis::setex($taskId, 3600 * 24, json_encode($progressData));

        } catch (\Exception $e) {
            \Log::error("更新Redis进度失败: " . $e->getMessage());
        }
    }


    private function processGoodsOption($goodsOption, $specProductTypes = [],$goodsItem)
    {
        if (empty($goodsOption)) {
            return [];
        }

        // 按规格分组，每个规格对应自己的选项
        $specHierarchy = [];
        $allSpecs = [];

        foreach ($goodsOption as $option) {
            $optionTitle = $option['option_title'] ?? '';

            // 解析option_title
            $parsedData = $this->parseOptionTitle($optionTitle);



            $specs = $parsedData['specs'];
            $specs_one = $parsedData['specs_one']?:$specs;
            $material = $parsedData['material'];
            $modelType = $parsedData['modelType'];
            $subMaterial = $parsedData['subMaterial']; // 新增：子材质（第三级）

            if (empty($specs)) {
                continue;
            }

            // 构建规格层级结构
            if (!isset($specHierarchy[$specs])) {
                $specHierarchy[$specs] = [
                    'materials' => [], // 第二级：尺寸
                    'subMaterials' => [], // 第三级：方向（左/右）
                    'productType' => $specProductTypes[$specs_one] ?? 5, // 默认常规商品
                    'first_category'=>$goodsItem['first_category'][$specs_one],
                    'two_category'=>$goodsItem['two_category'][$specs_one],

                ];
                $allSpecs[] = $specs;
            }

            // 存储材质（第二级）- 尺寸信息
            if (!empty($material) && !in_array($material, $specHierarchy[$specs]['materials'])) {
                $specHierarchy[$specs]['materials'][] = $material;
            }

            // 存储子材质（第三级）- 方向信息
            if (!empty($material) && !empty($subMaterial)) {
                if (!isset($specHierarchy[$specs]['subMaterials'][$material])) {
                    $specHierarchy[$specs]['subMaterials'][$material] = [];
                }
                if (!in_array($subMaterial, $specHierarchy[$specs]['subMaterials'][$material])) {
                    $specHierarchy[$specs]['subMaterials'][$material][] = $subMaterial;
                }
            }
        }

        // 构建 specs 格式
        $specsData = $this->buildSpecsData($specHierarchy, $allSpecs);

        // 构建 option 格式
        $optionData = $this->buildOptionData($goodsOption, $specHierarchy);

        return [
            'has_option' => 1,
            'specs' => $specsData,
            'option' => $optionData
        ];
    }


    /**
     * 增强的option_title解析方法 - 针对您的数据格式
     */
    private function parseOptionTitle($optionTitle)
    {

        $result = [
            'specs' => '',      // 第一级：单人位
            'material' => '',   // 第二级：1.4米x0.6米（尺寸）
            'subMaterial' => '', // 第三级：左/右（方向）
            'modelType' => 0
        ];


        $typeMap = [
            '独立位',
            '首位',
            '延伸位',
            '尾位',
            '十字型',
            'T字型',
            'L字型',
            'T字型-1',
            'L字型-1'
        ];

        if (empty($optionTitle)) {
            return $result;
        }

        // 针对格式：单人位_1.4米x0.6米_左(独立位)
        if (strpos($optionTitle, '_') !== false) {
            $parts = explode('_', $optionTitle);

            // 至少有3部分：规格_尺寸_方向(类型)
            if (count($parts) >= 3) {
                $result['specs'] = trim($parts[0]); // 单人位
                $result['material'] = trim($parts[1]); // 1.4米x0.6米
                $result['subMaterial'] = trim($parts[2]); // 左(独立位)

                // 从第三部分提取类型和纯方向
                if (preg_match('/^(.*?)\(([^()]+)\)$/', $result['subMaterial'], $matches)) {
                    $typeName = trim($matches[2]); // 获取括号内的内容

                    // 检查括号内的内容是否在 $typeMap 中
                    if (in_array($typeName, $typeMap)) {
                        $result['subMaterial'] = trim($matches[1]); // 左
                        $result['modelType'] = $this->mapTypeNameToModelType($typeName);
                    }
                    // 如果不在数组中，就不做任何处理，保持原样
                } else {
                    // 如果没有括号，整个作为subMaterial
                    $result['subMaterial'] = trim($result['subMaterial']);
                }
            } elseif (count($parts) == 2) {
                // 只有两部分的情况：规格_材质(类型)
                $result['specs'] = trim($parts[0]);
                $materialPart = trim($parts[1]);

                if (preg_match('/^(.*?)\(([^()]+)\)$/', $materialPart, $matches)) {

                    $typeName = trim($matches[2]);
                    if (in_array($typeName, $typeMap)) {
                        $result['material'] = trim($matches[1]);
                        $result['modelType'] = $this->mapTypeNameToModelType($typeName);
                    }else{
                        $result['material'] = $materialPart;
                    }

                } else {
                    $result['material'] = $materialPart;
                }
            }
        } else {
            // 无下划线的情况：整个作为规格


            if (preg_match('/^(.*?)\(([^()]+)\)$/', $optionTitle, $matches)) {


                $typeName = trim($matches[2]);
                if (in_array($typeName, $typeMap)) {
                    $result['specs'] = trim($matches[1]);
                    $result['modelType'] = $this->mapTypeNameToModelType($typeName);
                }else{
                    $result['specs'] = $optionTitle;
                }



            } else {
                $result['specs'] = $optionTitle;
            }
            $result['specs_one'] = $optionTitle;
            $result['material'] = "";


        }



        return $result;
    }




    /**
     * 映射类型名称到modelType
     */
    private function mapTypeNameToModelType($typeName)
    {
        $typeMap = [
            '独立位' => 0,
            '首位' => 1,
            '延伸位' => 2,
            '尾位' => 3,
            '十字型' => 0,
            'T字型' => 1,
            'L字型' => 2,
            'T字型-1' => 3,
            'L字型-1' => 4
        ];

        return $typeMap[$typeName] ?? 0;
    }




    /**
     * 构建规格树形数据（支持动态三级结构）
     */
    private function buildSpecsData($specHierarchy, $allSpecs)
    {
        if (empty($allSpecs)) {
            return [];
        }

        $specsData = [
            [
                "id" => "SC0",
                "title" => "款式",
                "initialTitle" => "款式",
                "spec_item" => []
            ]
        ];

        foreach ($allSpecs as $specIndex => $spec) {
            $spec_origin = $spec;
            $spec = $this->convertXtoAsterisk($spec);
            $productType = $specHierarchy[$spec_origin]['productType'] ?? 5;
            // 获取一级分类名称数组和二级分类名称数组
            $firstCategoryNames = $specHierarchy[$spec_origin]['first_category'] ?? [];
            $secondCategoryNames = $specHierarchy[$spec_origin]['two_category'] ?? [];

            // 构建分类数据结构
            $categoryData = $this->buildCategoryData($firstCategoryNames, $secondCategoryNames);
            $specItem = [
                "id" => "SV" . $specIndex . "&SC0",
                "parent_id" => 0,
                "parent_path_id" => "",
                "specid" => "SC0",
                "productType" => $productType,
                "title" => $spec,
                "initialTitle" => $spec,
                "level" => 1,
                "order_index" => $specIndex,
                'category' => $categoryData
            ];

            // 处理材质（第二级）- 尺寸信息
            $materials = $specHierarchy[$spec_origin]['materials'] ?? [];



            $children = [];

            foreach ($materials as $matIndex => $material) {

                if (empty($material)) continue;


                $materialOne = $material;
                $material = $this->convertXtoAsterisk($material);

                $childItem = [
                    "id" => "SV" . $specIndex . "&SC0&CH" . $matIndex,
                    "parent_id" => "SV" . $specIndex . "&SC0",
                    "parent_path_id" => "SV" . $specIndex . "&SC0",
                    "specid" => "SC0",
                    "title" => $material,
                    "level" => 2,
                    "order_index" => $matIndex,
                    "initialTitle" => $material
                ];

                // 处理子材质（第三级）- 方向信息（左/右）

                $subMaterials = $specHierarchy[$spec_origin]['subMaterials'][$materialOne] ?? [];

                $subChildren = [];

                foreach ($subMaterials as $subMatIndex => $subMaterial) {
                    if (empty($subMaterial)) continue;

                    $subChildItem = [
                        "id" => "SV" . $specIndex . "&SC0&CH" . $matIndex . "&CH" . $subMatIndex,
                        "parent_id" => "SV" . $specIndex . "&SC0&CH" . $matIndex,
                        "parent_path_id" => "SV" . $specIndex . "&SC0_SV" . $specIndex . "&SC0&CH" . $matIndex,
                        "specid" => "SC0",
                        "title" => $subMaterial,
                        "level" => 3,
                        "order_index" => $subMatIndex,
                        "initialTitle" => $subMaterial
                    ];
                    $subChildren[] = $subChildItem;
                }

                // 如果有子材质，添加children字段
                if (!empty($subChildren)) {
                    $childItem['children'] = $subChildren;
                }

                $children[] = $childItem;
            }

            // 如果有材质，添加children字段到specItem
            if (!empty($children)) {
                $specItem['children'] = $children;
            }

            $specsData[0]['spec_item'][] = $specItem;
        }

        return $specsData;
    }


    /**
     * 构建分类数据结构
     * @param array $firstCategoryNames 一级分类名称数组
     * @param array $secondCategoryNames 二级分类名称数组
     * @return array 返回格式化的分类数据
     */
    private function buildCategoryData($firstCategoryNames, $secondCategoryNames)
    {
        if (empty($firstCategoryNames) || empty($secondCategoryNames)) {
            return [];
        }

        // 获取所有一级分类ID
        $firstCategories = Category::whereIn('name', $firstCategoryNames)
            ->where('level', 1)  // 假设有level字段标识分类级别
            ->get(['id', 'name'])
            ->keyBy('name')
            ->toArray();

        // 获取所有二级分类ID
        $secondCategories = Category::whereIn('name', $secondCategoryNames)
            ->where('level', 2)  // 假设有level字段标识分类级别
            ->get(['id', 'name', 'parent_id'])
            ->toArray();

        if (empty($firstCategories) || empty($secondCategories)) {
            return [];
        }

        // 按parent_id分组二级分类
        $secondByParent = [];
        foreach ($secondCategories as $second) {
            $parentId = $second['parent_id'];
            if (!isset($secondByParent[$parentId])) {
                $secondByParent[$parentId] = [];
            }
            $secondByParent[$parentId][] = $second['id'];
        }

        // 构建最终的数据结构
        $categoryData = [];
        foreach ($firstCategories as $firstName => $first) {
            $firstId = $first['id'];

            // 获取当前一级分类下的二级分类ID
            $secondIds = $secondByParent[$firstId] ?? [];

            if (!empty($secondIds)) {
                $categoryData[] = [
                    'first_id' => $firstId,
                    'second_ids' => $secondIds
                ];
            }
        }

        return $categoryData;
    }


    /**
     * 构建选项数据
     */
    private function buildOptionData($goodsOption, $specHierarchy)
    {
        $optionData = [];
        $optionIndex = 0;

        // 首先按规格分组
        $groupedOptions = [];

        foreach ($goodsOption as $option) {
            $optionTitle = $option['option_title'] ?? '';
            $parsedData = $this->parseOptionTitle($optionTitle);

            $specs = $parsedData['specs'];
            $material = $parsedData['material'];
            $subMaterial = $parsedData['subMaterial'];
            $modelType = $parsedData['modelType'];

            if (!empty($specs)) {
                $productType = $specHierarchy[$specs]['productType'] ?? 5;

                // 分组键：规格_尺寸_方向
                if($specs && $material && $subMaterial){
                    $groupKey = $specs . '_' . $material . '_' . $subMaterial;
                }elseif($specs && $material){
                    $groupKey = $specs . '_' . $material;
                }elseif($specs){
                    $groupKey = $specs;
                }

                if (!isset($groupedOptions[$groupKey])) {
                    $groupedOptions[$groupKey] = [
                        'specs' => $specs,
                        'material' => $material,
                        'subMaterial' => $subMaterial,
                        'productType' => $productType,
                        'options' => []
                    ];
                }

                $groupedOptions[$groupKey]['options'][] = [
                    'modelType' => $modelType,
                    'optionTitle' => $optionTitle,
                    'data' => $option
                ];
            }
        }

        // 构建optionData
        foreach ($groupedOptions as $groupKey => $group) {
            $specs = $group['specs'];
            $material = $group['material'];
            $subMaterial = $group['subMaterial'];
            $productType = $group['productType'];

            // 找到对应的索引
            $specKeys = array_keys($specHierarchy);
            $specIndex = array_search($specs, $specKeys);

            if ($specIndex !== false) {
                $materials = $specHierarchy[$specs]['materials'] ?? [];
                $matIndex = array_search($material, $materials);

                if ($matIndex !== false) {
                    // 二级或三级结构
                    $specId = "SV" . $specIndex . "&SC0";
                    $childId = "SV" . $specIndex . "&SC0&CH" . $matIndex;

                    // 查找子材质索引
                    $subMaterials = $specHierarchy[$specs]['subMaterials'][$material] ?? [];
                    $subMatIndex = array_search($subMaterial, $subMaterials);

                    if ($subMatIndex !== false && !empty($subMaterial)) {
                        // 三级结构
                        $subChildId = "SV" . $specIndex . "&SC0&CH" . $matIndex . "&CH" . $subMatIndex;
                        $specItemId = $specId . "_" . $childId . "_" . $subChildId;
                        $baseTitle = $specs . '/' . $material . '/' . $subMaterial;
                    } else {
                        // 二级结构
                        $specItemId = $specId . "_" . $childId;
                        $baseTitle = $specs . '/' . $material;
                    }
                } else {
                    // 一级结构 - 只有规格，没有材质
                    $specId = "SV" . $specIndex . "&SC0";
                    $specItemId = $specId;
                    $baseTitle = $specs;
                }

                $modelTypes = [];
                foreach ($group['options'] as $optionItem) {
                    $titleSuffix = $this->getModelTypeTitle($optionItem['modelType'], $productType);

                    $fullTitle = $baseTitle . $titleSuffix;

                    $modelTypes[] = [
                        "modelType" => $optionItem['modelType'],
                        "title" => $fullTitle,
                        "option" => $this->buildOptionItem(
                            $optionItem['data'],
                            $optionIndex,
                            $specItemId,
                            !empty($subMaterial) ? $subMaterial : (!empty($material) ? $material : $specs),
                            $optionItem['modelType'],
                            $fullTitle
                        )
                    ];
                }

                $optionItem = [
                    "activeTab" => (string)$productType,
                    "spec_item_id" => $specItemId,
                    "productType" => $productType,
                    "modelTypes" => $modelTypes
                ];

                $optionData[] = $optionItem;
                $optionIndex++;
            }
        }

        return $optionData;
    }


    /**
     * 根据productType获取modelTypes配置
     */
    /**
     * 根据productType获取modelTypes配置
     */
    private function getModelTypesConfig($productType, $title)
    {
        $modelTypesConfig = [
            2 => [ // 拼接辅助
                ['modelType' => 0, 'title' => $title . '(独立位)'],
                ['modelType' => 2, 'title' => $title . '(延伸位)'],
            ],
            3 => [ // 多元拼接
                ['modelType' => 0, 'title' => $title . '(独立位)'],
                ['modelType' => 1, 'title' => $title . '(首位)'],
                ['modelType' => 2, 'title' => $title . '(延伸位)'],
                ['modelType' => 3, 'title' => $title . '(尾位)'],
            ],
            4 => [ // 屏风卡位
                ['modelType' => 0, 'title' => $title . '(十字型)'],
                ['modelType' => 1, 'title' => $title . '(T字型)'],
                ['modelType' => 2, 'title' => $title . '(L字型)'],
                ['modelType' => 3, 'title' => $title . '(T字型-1)'],
                ['modelType' => 4, 'title' => $title . '(L字型-1)'],
            ],
            5 => [['modelType' => 0, 'title' => $title]], // 常规商品
        ];

        return $modelTypesConfig[$productType] ?? $modelTypesConfig[5];
    }

    /**
     * 根据modelType和productType获取类型标题
     */
    private function getModelTypeTitle($modelType, $productType)
    {
        $typeMap = [
            2 => [ // 拼接辅助
                0 => '(独立位)',
                2 => '(延伸位)'
            ],
            3 => [ // 多元拼接
                0 => '(独立位)',
                1 => '(首位)',
                2 => '(延伸位)',
                3 => '(尾位)'
            ],
            4 => [ // 屏风卡位
                0 => '(十字型)',
                1 => '(T字型)',
                2 => '(L字型)',
                3 => '(T字型-1)',
                4 => '(L字型-1)'
            ],
            5 => [ // 常规商品
                0 => ''
            ]
        ];

        $map = $typeMap[$productType] ?? $typeMap[5];
        return $map[$modelType] ?? $map[0];
    }


    private function convertXtoAsterisk($filename) {
        // 匹配模式：数字x数字x数字
        $pattern = '/(\d+)x(\d+)x(\d+)/';

        if (preg_match($pattern, $filename)) {
            // 如果匹配到 数字x数字x数字 的模式，就进行转换
            $converted = preg_replace('/x/', '*', $filename);
            return $converted;
        }

        return $filename; // 如果没有匹配，返回原文件名
    }



    private function buildOptionItem($option, $index, $specItemId, $fullTitle, $modelType, $title)
    {

        $spec_option_dir = $this->relativeFolder . self::SPECIFICATIONS;

        // $package_option_data = $this->processPackageOption($option['package_option']);

        // 初始化文件路径
        $dwg_upload_url = "";
        $d3max_url = "";
        $thumb_data = ['thumb' => '', 'thumb_url' => ''];

        // 提取标题中的括号内容（如果有），并去掉括号部分得到纯净的标题
        $folderPattern = '';

        if (preg_match('/.*[\(（]([^\)）]*)[\)）]$/', $title, $matches)) {
            $folderPattern = $matches[1]; // 结果：独立位
        }

        $title_da = explode("/",$title);

        // 构建标题对应的文件夹路径（使用去掉括号的标题）
        $titleFolder = $spec_option_dir . '/' . implode("_",$title_da);



        \Log::error("查找规格文件夹: {$titleFolder}, 括号内容: {$folderPattern}");


        // 定义安装指南和走线图目录
        $installationGuideDir = $titleFolder . '/安装指南';
        $wiringDiagramDir = $titleFolder . '/走线示意图';
        $dwgFile = null;
        $maxFile = null;
        $imageFile = null;
        $dwgOriginalName= "";
        $maxOriginalName="";
        $imageOriginalName="";
        $install_guide_url="";
        $install_guide = [];
        $goods_wiring_diagram=[];
        // 检查标题对应的文件夹是否存在

        if (file_exists($titleFolder) && is_dir($titleFolder)) {
            $files = scandir($titleFolder);
            $files = array_filter($files, function($file) {
                return $file !== '.' && $file !== '..';
            });

            // 1. 首先检查标题文件夹根目录是否有3个文件




            foreach ($files as $file) {
                $filePath = $titleFolder . '/' . $file;
                $fileInfo = pathinfo($file);
                $extension = strtolower($fileInfo['extension'] ?? '');

                if (is_file($filePath)) {
                    if ($extension === 'dwg') {
                        $dwgFile = $filePath;
                        $dwgOriginalName = $file;
                    } elseif ($extension === 'max') {
                        $maxFile = $filePath;
                        $maxOriginalName = $file;
                    } elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
                        $imageFile = $filePath;
                        $imageOriginalName = $file;
                    }
                }
            }

            if($imageFile){
                \Log::error("在根目录找到图片文件: {$imageFile}");
                $thumb_data = $this->processSingleImage($imageFile);
            }

            // 如果根目录找到3个文件，直接使用
            if ($dwgFile && $maxFile) {
                \Log::error("在根目录找到3个文件: {$dwgFile}, {$maxFile}");
                // 上传图片文件


                // 上传DWG文件
                $res = uploadOss($dwgFile, uniqid() . ".dwg", 0);
                $dwg_upload_url = $res['relative_path'] ?? "";



                // 上传MAX文件
                $res = uploadOss($maxFile, uniqid() . ".max", 0);
                $d3max_url = $res['relative_path'] ?? "";


            }else {
                \Log::debug("根目录未找到3个文件且无括号内容，无法查找子目录");
            }


              // 2. 检查安装指南目录是否存在并扫描文件
            if (file_exists($installationGuideDir) && is_dir($installationGuideDir)) {
                \Log::error("找到安装指南目录: {$installationGuideDir}");
              
                
                $install_guide_data = $this->processInstallGuide($installationGuideDir);

                $install_guide_url= !empty($install_guide_data) ? $install_guide_data['pdf_file'] : "";
                $install_guide = !empty($install_guide_data) ? $install_guide_data['data'] : [];

                
            } else {
                \Log::debug("安装指南目录不存在: {$installationGuideDir}");
            }

            // 3. 检查走线图目录是否存在并扫描文件
            if (file_exists($wiringDiagramDir) && is_dir($wiringDiagramDir)) {
                \Log::error("找到走线图目录: {$wiringDiagramDir}");
               $goods_wiring_diagram = $this->processImage($wiringDiagramDir, 'detail');
                
                
            } else {
                \Log::debug("走线图目录不存在: {$wiringDiagramDir}");
            }

        } else {
            \Log::debug("规格目录不存在: {$titleFolder}");
        }



        $spec_data = explode("*",$option['specs']);
        return [
            "id" => "SP" . $index . "_" . $modelType,
            "uniacid" => $this->uniacid,
            "title" => $title, // 返回原始标题（包含括号）
            "specs" => $specItemId,
            "product_price" => round($option['product_price']) ?? "",
            "singleType" => $option['singleType'] ?? null,
            "product_model" => $option['product_model'] ?? "",
            "structure" => $option['structure'] ?? "",
            "length" => $spec_data[0],
            "width" => $spec_data[1],
            "height" =>  $spec_data[2],
            "package_number" => $option['package_number'] ?? 1,
            "package_option" => [],
            "d3MaxUrl" => $d3max_url,
            "d3model_url" => "",
            "cad_plan_model" => $dwg_upload_url,
            "thumb" => $thumb_data['thumb'] ?? "",
            "model_param" => [],
            "thumb_url" => $thumb_data['thumb_url'] ?? "",
            "volume" => $option['volume'] ?: 0,
            "d3MaxName"=>$maxOriginalName,
            'cad_plan_modelName'=>$dwgOriginalName,
            'thumbName'=>$imageOriginalName,
            'install_guide_url'=>$install_guide_url,
            "install_guide"=>$install_guide,
            "wiring_diagram"=>$goods_wiring_diagram,

        ];
    }

    /**
     * 处理单张图片上传
     */
    private function processSingleImage($imagePath)
    {
        /*$res = uploadOssV2($imagePath, uniqid() . ".png", 1);

        return [
            'thumb' => $res['relative_path'] ?? '',
            'thumb_url' => $res['absolute_path'] ?? ''
        ];*/

        $res = uploadOssV2($imagePath, uniqid() . ".png", 1, "detail");
        \Log::error("====图片是否上传成功====",['res'=>$res,'image_path'=>$imagePath]);
        $thumb_url = [
            'thumb' => $res['relative_path'],
            'main_thumb' => $res['relative_path'],
            'high_url' => $res['high_relative_path'],
        ];

        return ['thumb_url' => $thumb_url, 'thumb' => $thumb_url['thumb']];
    }




    /**
     * 处理包装选项数据
     */
    public function processPackageOption($packageOption)
    {
        if (empty($packageOption)) {
            return [
                'package_option' => [],
                'total_volume' => 0,
                'package_number' => 0,
                'dimensions' => [
                    'length' => 0,
                    'width' => 0,
                    'height' => 0
                ]
            ];
        }

        $packageData = [];
        $totalVolume = 0;

        // 处理多包装格式（|分隔）
        if (strpos($packageOption, '|') !== false) {
            $packages = explode('|', $packageOption);
            foreach ($packages as $package) {
                $packageData[] = $this->parseSinglePackage($package);
            }
        } else {
            // 处理单包装格式
            $packageData[] = $this->parseSinglePackage($packageOption);
        }

        // 计算总体积
        foreach ($packageData as $pkg) {
            $totalVolume += $pkg['volume'];
        }

        return [
            'package_option' => $packageData,
            'total_volume' => round($totalVolume, 4), // 保留4位小数
            'package_number' => count($packageData),
            'dimensions' => $packageData[0] ?? [
                    'length' => 0,
                    'width' => 0,
                    'height' => 0
                ]
        ];
    }

    /**
     * 解析单个包装字符串
     */
    private function parseSinglePackage($packageStr)
    {
        $dimensions = explode('x', $packageStr);

        $length = floatval($dimensions[0] ?? 0);
        $width = floatval($dimensions[1] ?? 0);
        $height = floatval($dimensions[2] ?? 0);

        // 计算体积（立方毫米转立方米）
        $volume = ($length * $width * $height) / 1000000000;

        return [
            'length' => $length,
            'width' => $width,
            'height' => $height,
            'volume' => round($volume, 6) // 保留6位小数
        ];
    }


    private function processOptionThumb($title)
    {
        if (empty($title)) {
            return [];
        }

        // 定义支持的图片格式
        $imageExtensions = ['jpg', 'png', 'jpeg', 'webp', 'gif'];
        $imagePath = '';
        $foundExtension = '';

        // 检查各种图片格式是否存在
        foreach ($imageExtensions as $extension) {
            $checkPath = $this->fileFolder . $title . "." . $extension;
            if (file_exists($checkPath)) {
                $imagePath = $checkPath;
                $foundExtension = $extension;
                break;
            }
        }

        // 如果没有找到任何图片文件
        if (empty($imagePath)) {
            // 可以根据需要返回空数组或进行其他处理
            return [];
            // 或者抛出异常：throw new Exception("图片文件不存在: " . $title);
        }

        // 使用找到的文件扩展名进行上传
        $res = uploadOssV2($imagePath, uniqid() . "." . $foundExtension, 1, "detail");

        $thumb_url = [
            'thumb' => $res['relative_path'],
            'main_thumb' => $res['relative_path'],
            'high_url' => $res['high_relative_path'],
        ];

        return ['thumb_url' => $thumb_url, 'thumb' => $thumb_url['thumb']];
    }


    /**
     * 获取目录中的所有图片文件
     *
     * @param string $directory 要扫描的目录路径
     * @return array 图片文件数组
     */
    private function getImageFiles($directory)
    {
        $files = [];

        // 检查目录是否存在
        if (file_exists($directory) && is_dir($directory)) {
            $files = scandir($directory);
            $files = array_filter($files, function($file) {
                return $file !== '.' && $file !== '..';
            });
            $files = array_values($files); // 重新索引数组
        }

        // 过滤出图片文件
        $imageFiles = [];

        foreach ($files as $file) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
                $imageFiles[] = $file;
            }
        }

        return $imageFiles;
    }

    /**
     * 处理商品主图片
     */
    private function processMainImage($relativeFolder, $thumb_type)
    {
        $image_dir = $relativeFolder . self::MAIN_PRODUCT_IMAGE;

        // 使用封装好的函数获取图片文件
        $goods_images = $this->getImageFiles($image_dir);

        if (empty($goods_images)) {
            return [];
        }

        $result = [];
        $thumb = "";
        $imageFolder = $image_dir . '/'; // 假设图片文件夹路径

        foreach ($goods_images as $key => $imageFile) {
            $imagePath = $imageFolder . $imageFile;
            $res = uploadOssV2($imagePath, uniqid() . ".png", 1, $thumb_type);

            if ($key == 0) {
                $thumb = $res['webp_thumb_path'];
            }

            $arr = [
                'thumb_link' => $thumb_type == "detail" ? $res['webp_absolute_path'] : $res['webp_thumb_absolute_path'],
                'thumb' => $thumb_type == "detail" ? $res['webp_path'] : $res['webp_thumb_path'],
                'main_thumb' => $res['webp_path'],
                'high_url' => $res['high_relative_path'],
            ];
            $result[] = $arr;
        }

        return ['main_url' => $result, 'thumb' => $thumb];
    }

    /**
     * 获取目录中的所有视频文件
     *
     * @param string $directory 要扫描的目录路径
     * @return array 视频文件数组
     */
    private function getVideoFiles($directory)
    {
        $files = [];

        // 检查目录是否存在
        if (file_exists($directory) && is_dir($directory)) {
            $files = scandir($directory);
            $files = array_filter($files, function($file) {
                return $file !== '.' && $file !== '..';
            });
            $files = array_values($files); // 重新索引数组
        }

        // 支持的视频格式
        $videoTypes = [
            'avi', 'asf', 'wmv', 'avs', 'flv', 'mkv', 'mov', '3gp', 'mp4', 'mpg', 'mpeg',
            'dat', 'ogm', 'vob', 'rm', 'rmvb', 'ts', 'tp', 'ifo', 'nsv'
        ];

        // 过滤出视频文件
        $videoFiles = [];

        foreach ($files as $file) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if (in_array($extension, $videoTypes)) {
                $videoFiles[] = $file;
            }
        }

        return $videoFiles;
    }

    /**
     * 处理商品视频
     */
    private function processVideo($video_dir)
    {


        // 使用封装好的函数获取视频文件
        $videoFiles = $this->getVideoFiles($video_dir);

        if (empty($videoFiles)) {
            return "";
        }

        // 只处理第一个视频文件
        $videoFile = $videoFiles[0];
        $videoPath = $video_dir . '/' . $videoFile;

        $res = uploadOssV2($videoPath, uniqid() . ".mp4", 0, "detail", "video");

        return $res['relative_path'] ?: "";
    }


    /**
     * 处理商品图片
     */

    private function processImage($image_dir, $thumb_type)
    {

        // 使用封装好的函数获取图片文件
        $goods_images = $this->getImageFiles($image_dir);

        if (empty($goods_images)) {
            return [];
        }

        $result = [];
        foreach ($goods_images as $imageFile) {
            $imagePath = $image_dir ."/". $imageFile;
            $res = uploadOssV2($imagePath, uniqid() . ".png", 1, $thumb_type);

//            $res['webp_absolute_path'] = $res['webp_absolute_path'];
//            $res['webp_path'] = $res['webp_path'];
            $arr = [
                'thumb_link' => $thumb_type == "detail" ? $res['absolute_path'] : $res['webp_thumb_absolute_path'],
                'thumb' => $thumb_type == "detail" ? $res['absolute_path'] : $res['webp_thumb_path'],
                'main_thumb' => $thumb_type == "detail"?$res['absolute_path']:$res['webp_path'],
                'high_url' => $res['high_relative_path'],
            ];

            $result[] = $arr;
        }

        
        $this->deleteImagesByKeywords($image_dir);
        return $result;
    }


        /**
     * 删除目录中包含指定关键词的图片文件
     */
    private function deleteImagesByKeywords($directory)
    {
        $keywords = ['thumb', 'medium_thumb', 'goodsmain', 'main', 'detail', 'original'];
        if (!is_dir($directory)) {
            return;
        }

        $files = scandir($directory);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $directory . '/' . $file;
            
            // 只处理文件，不处理目录
            if (is_file($filePath)) {
                foreach ($keywords as $keyword) {
                    if (strpos($file, $keyword) !== false) {
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                        break; // 找到一个匹配的关键词就删除并跳出内层循环
                    }
                }
            }
        }
    }


    /**
     * 处理安装指南或上传手册
     */

    private function processInstallGuide($filedir)
    {
        // 获取目录中的PDF和图片文件
        $fileTypes = $this->getPdfAndImageFiles($filedir);
        $pdfFiles = $fileTypes['pdfFiles'];
        $imageFiles = $fileTypes['imageFiles'];

        if (empty($pdfFiles) && empty($imageFiles)) {
            return [];
        }

        $result = [];
        $file_path = "";
        $isPdf = 0;
        $fileFolder = $filedir . '/';
        $imageFolder = $filedir . '/';

        // 优先处理PDF文件（只处理第一个PDF）
        if (!empty($pdfFiles)) {
            $pdfFile = $pdfFiles[0];
            $pdfPath = $fileFolder . $pdfFile;

            $res = uploadOss($pdfPath, uniqid() . ".pdf", 0);
            if (!$res || !isset($res['absolute_path'])) {
                \Log::error("上传PDF到OSS失败: " . $pdfPath);
                return [];
            }

            $file_path = yz_tomedia($res['absolute_path']);

            // 调用Python接口处理PDF
            $params = [
                'pdf_file' => $res['absolute_path'],
                'output' => storage_path("app/public/pdfimg"),
            ];

            $processedData = processDwg("processInstallPdf", $params);
            if ($processedData['data']['status_code'] == 200) {
                foreach ($processedData['data']['image_paths'] as $item) {
                    $res = uploadOssV2($item['image_path'], uniqid() . ".png", 1);
                    $arr = [
                        'thumb_link' => $res['absolute_path'],
                        'thumb' => $res['relative_path'],
                        'main_thumb' => $res['relative_path'],
                        'high_url' => $res['high_relative_path'],
                    ];
                    $result[] = $arr;
                }
            }
            $isPdf = 1;

        } else {
            // 处理多张图片
            $currentHighUrls = [];

            foreach ($imageFiles as $imageFile) {
                $imagePath = $imageFolder . $imageFile;

                // 上传图片到OSS
                $res = uploadOssV2($imagePath, uniqid() . ".png", 1);

                if ($res && isset($res['absolute_path'])) {
                    $arr = [
                        'thumb_link' => $res['absolute_path'],
                        'thumb' => $res['relative_path'],
                        'main_thumb' => $res['relative_path'],
                        'high_url' => $res['high_relative_path'],
                    ];

                    $result[] = $arr;

                    // 收集所有图片的高清URL用于生成PDF
                    $currentHighUrls[] = yz_tomedia($res['high_relative_path']);
                }
            }

            // 根据图片生成PDF
            if (!empty($currentHighUrls)) {
                $output = storage_path("app/public/pdfimg/" . uniqid() . "_install.pdf");
                // 确保目录存在
                if (!file_exists(dirname($output))) {
                    mkdir(dirname($output), 0755, true);
                }

                $params = [
                    'image_urls' => $currentHighUrls,
                    'output_pdf' => $output,
                ];

                $response = processDwg("generatePdf", $params);

                if ($response['data']['status_code'] == 200 && file_exists($output)) {
                    // 上传生成的PDF到OSS
                    $pdfRes = uploadOss($output, uniqid() . "_install.pdf", 0);
                    if ($pdfRes && isset($pdfRes['absolute_path'])) {
                        $file_path = yz_tomedia($pdfRes['absolute_path']);
                    }
                } else {
                    \Log::error("生成 PDF 失败: " . json_encode($response));
                }

                // 清理临时文件
                if (file_exists($output)) {
                    unlink($output);
                }
            }
            $isPdf = 0;
        }
        $this->deleteImagesByKeywords($filedir);
        return [
            'data' => [
                'data' => $result,
                'isPdf' => $isPdf
            ],
            'pdf_file' => $file_path
        ];
    }



    /**
     * 获取目录中的PDF和图片文件
     *
     * @param string $directory 要扫描的目录路径
     * @return array 包含pdfFiles和imageFiles两个键的数组
     */
    private function getPdfAndImageFiles($directory)
    {
        $files = [];

        // 检查目录是否存在
        if (file_exists($directory) && is_dir($directory)) {
            $files = scandir($directory);
            $files = array_filter($files, function($file) {
                return $file !== '.' && $file !== '..';
            });
            $files = array_values($files); // 重新索引数组
        }

        // 分离PDF和图片文件
        $pdfFiles = [];
        $imageFiles = [];

        foreach ($files as $file) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if ($extension === 'pdf') {
                $pdfFiles[] = $file;
            } elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
                $imageFiles[] = $file;
            }
        }

        return [
            'pdfFiles' => $pdfFiles,
            'imageFiles' => $imageFiles
        ];
    }


    private function processECatalogPdf($relativeFolder)
    {
        $pdfdir = $relativeFolder . self::PRODUCT_CATALOG;

        // 使用封装好的函数获取文件
        $fileTypes = $this->getPdfAndImageFiles($pdfdir);
        $pdfFiles = $fileTypes['pdfFiles'];
        $imageFiles = $fileTypes['imageFiles'];

        // 优先处理PDF文件（只处理第一个PDF）
        if (!empty($pdfFiles)) {
            $pdfFile = $pdfFiles[0]; // 只取第一个PDF文件
            $pdfPath = $pdfdir . '/' . $pdfFile;

            return $this->processPdfFile($pdfPath);
        }
        // 如果没有PDF但有图片，则处理图片
        elseif (!empty($imageFiles)) {
            return $this->processImageFiles($pdfdir, $imageFiles);
        }
        // 如果既没有PDF也没有图片
        else {
            return [
                'atlas_data' => json_encode(['data' => [], 'isPdf' => 0]),
                'e_catalog_pdf' => '',
                'pdf_page_img' => []
            ];
        }
    }

    /**
     * 处理单个PDF文件
     */
    private function processPdfFile($pdfPath)
    {
        $isPdf = 0;
        $eCatalogPdfPath = '';
        $pdfPageImg = [];
        $result = [];

        // 上传PDF到OSS
        $res = uploadOss($pdfPath, uniqid() . ".pdf", 0);

        if (!$res || !isset($res['absolute_path'])) {
            \Log::error("上传PDF到OSS失败: " . $pdfPath);
            return [
                'atlas_data' => json_encode(['data' => [], 'isPdf' => $isPdf]),
                'e_catalog_pdf' => '',
                'pdf_page_img' => []
            ];
        }

        $eCatalogPdfPath = yz_tomedia($res['absolute_path']);


        // 调用Python接口处理PDF
        $params = [
            'pdf_file' => $res['absolute_path'],
            'output' => storage_path("app/public/pdfimg"),
        ];

        $processedData = processDwg("processPdf", $params);

        if ($processedData['data']['status_code'] == 200) {
            foreach ($processedData['data']['image_paths'] as $item) {
                $pdfPageImg = array_column($processedData['data']['image_paths'], 'image_high_path');
                $res = uploadOssV2($item['image_path'], uniqid() . ".png", 1);
                $arr = [
                    'thumb_link' => $res['absolute_path'],
                    'thumb' => $res['relative_path'],
                    'main_thumb' => $res['relative_path'],
                    'high_url' => $res['high_relative_path'],
                    'inPpt' => $item['in_ppt'] ?? 0
                ];
                $result[] = $arr;
            }
        }

        return [
            'atlas' => ['data' => $result, 'isPdf' => $isPdf, 'is_not' => 1],
            'e_catalog_pdf' => $eCatalogPdfPath,
            'pdf_page_img' => $pdfPageImg
        ];
    }

    /**
     * 处理多个图片文件
     */
    private function processImageFiles($pdfdir, $imageFiles)
    {
        $isPdf = 0;
        $eCatalogPdfPath = '';
        $pdfPageImg = [];
        $result = [];
        $currentHighUrls = [];
        $currentPPTUrls = [];

        // 处理多张图片
        $isFirstImage = true; // 标记是否为第一张图片
        foreach ($imageFiles as $imageFile) {
            $imagePath = $pdfdir . '/' . $imageFile;
            \Log::error("===imagePath====",$imagePath);
            // 上传图片到OSS
            $res = uploadOssV2($imagePath, uniqid() . ".png", 1);
            \Log::error("====ImageFile===",$res);
            if ($res && isset($res['absolute_path'])) {
                // 只有第一张图片 inPpt=1，其他都是0
                $inPpt = $isFirstImage ? 1 : 0;
                $res['absolute_path'] = $res['absolute_path'];
                $arr = [
                    'thumb_link' => $res['absolute_path'],
                    'thumb' => $res['relative_path'],
                    'main_thumb' => $res['relative_path'],
                    'high_url' => $res['high_relative_path'],
                    'inPpt' => $inPpt
                ];

                $result[] = $arr;

                // 只有第一张图片的高清URL添加到currentHighUrls
                $currentHighUrls[] = $res['high_relative_path']?yz_tomedia($res['high_relative_path']):null;
                if ($inPpt == 1) {
                    $currentPPTUrls[] = $res['high_relative_path']?yz_tomedia($res['high_relative_path']):null;
                }

                // 处理完第一张后，将标记设为false
                $isFirstImage = false;
            }
        }

        if ($currentPPTUrls) {
            $pdfPageImg = $this->downPptImg($currentPPTUrls);
        }

        // 根据图片生成PDF
        if (!empty($currentHighUrls)) {
            $output = storage_path("app/public/pdfimg/" . uniqid() . "_atlas.pdf");
            // 确保目录存在
            if (!file_exists(dirname($output))) {
                mkdir(dirname($output), 0755, true);
            }

            $params = [
                'image_urls' => $currentHighUrls,
                'output_pdf' => $output,
            ];

            $response = processDwg("generatePdf", $params);

            if ($response['data']['status_code'] == 200 && file_exists($output)) {
                // 上传生成的PDF到OSS
                $pdfRes = uploadOss($output, uniqid() . "_atlas.pdf", 0);
                if ($pdfRes && isset($pdfRes['absolute_path'])) {
                    $eCatalogPdfPath = yz_tomedia($pdfRes['absolute_path']);
                }
            } else {
                \Log::error("生成 PDF 失败: " . json_encode($response));
            }

            // 清理临时文件
            if (file_exists($output)) {
                unlink($output);
            }
        }
        $this->deleteImagesByKeywords($pdfdir);

        return [
            'atlas' => ['data' => $result, 'isPdf' => $isPdf, 'is_not' => 1],
            'e_catalog_pdf' => $eCatalogPdfPath,
            'pdf_page_img' => $pdfPageImg
        ];
    }


    private function downPptImg($currentPPTUrls)
    {


        if (!$currentPPTUrls) {
            return [];
        }
        $params = [
            'image_urls' => $currentPPTUrls,
        ];

        // 调用Python接口下载图片
        $response = processDwg("downloadHighImages", $params);

        if ($response['data']['status_code'] != 200) {
            \Log::error("下载高清图片失败: " . json_encode($response));
            return [];
        }

        // 获取下载的图片路径
        $downloadedImages = $response['data']['downloaded_images'] ?? [];

        return $downloadedImages;
    }

    /**
     * 批量处理分类数据
     */
    private function processCategoryDataBatch($cateNames, $cateParentNames, $categoryMap)
    {
        $categoryData = [];
        $processedLevel1 = []; // 记录已经处理过的一级分类

        foreach ($cateNames as $index => $cateName) {
            $cleanName = trim($cateName);
            $cleanParentName = isset($cateParentNames[$index]) ? trim($cateParentNames[$index]) : '';

            if (isset($categoryMap[$cleanName])) {
                $level1Id = $categoryMap[$cleanName]['id'];

                // 如果这个一级分类还没有处理过
                if (!isset($processedLevel1[$level1Id])) {
                    $levelData = [];

                    // 一级分类
                    $levelData[] = [
                        'id' => $level1Id,
                        'level' => 1
                    ];

                    // 收集所有属于这个一级分类的二级分类ID
                    $level2Ids = [];
                    foreach ($cateParentNames as $parentIndex => $parentName) {
                        $cleanParent = trim($parentName);
                        if (isset($categoryMap[$cleanParent]) &&
                            $categoryMap[$cleanParent]['parent_id'] == $level1Id) {
                            $level2Ids[] = $categoryMap[$cleanParent]['id'];
                        }
                    }

                    $levelData[] = [
                        'id' => array_unique($level2Ids),
                        'level' => 2
                    ];

                    $categoryData[] = $levelData;
                    $processedLevel1[$level1Id] = true;
                }
            }
        }

        return $categoryData;
    }

    /**
     * 批量处理名称到ID的转换
     */
    private function processNamesToIdsBatch($names, $map)
    {
        $ids = [];

        foreach ($names as $name) {
            $cleanName = trim($name);
            if (isset($map[$cleanName])) {
                $ids[] = $map[$cleanName]['id'];
            }
        }

        return $ids;
    }


    public function combineExcelData($excelData)
    {


        $mainGoodsData = $this->parseExcelData($excelData[0]);


        $goodsOptions = $this->parseGoodsOptions($excelData[1]);



        foreach ($mainGoodsData as &$goods) {
            $goodsTitle = $goods['goods_title'] ?? '';
            if ($goodsTitle && isset($goodsOptions[$goodsTitle])) {
                $goods['goods_option'] = $goodsOptions[$goodsTitle];
                $goods['spec_product'] = $goodsOptions['spec_product_types'][$goodsTitle];
                $goods['first_category'] = $goodsOptions['spec_first_categories'][$goodsTitle];
                $goods['two_category'] = $goodsOptions['spec_two_categories'][$goodsTitle];
            }
        }

        return $mainGoodsData;

    }

    public function parseExcelData($data)
    {
        $headerRow = $data[0];
        $dataRows = array_slice($data, 1); // 获取所有数据行（从索引1开始）

        // 字段映射配置
        $fieldConfig = [
            'goods_title' => 'goods_title',
            'keywords'=>'keywords',
            'cate_name' => 'cate_name',
            'cate_parent_name' => 'cate_parent_name',
            'process_material' => 'process_material',
            'goods_style' => 'goods_style',
            'sku' => 'sku',
            'warranty' => 'warranty',
            'is_stock' => 'is_stock',
            'stock' => 'stock',
            'lead_time' => 'lead_time',
        ];

        // 需要拆分为数组的字段
        $arrayFields = [
            'cate_name', 'cate_parent_name', 'process_material', 'goods_style',
        ];

        // 创建字段映射
        $fieldMapping = [];
        foreach ($headerRow as $index => $header) {
            $cleanHeader = trim(str_replace(["\n", "\r"], '', $header));
            foreach ($fieldConfig as $field => $pattern) {
                if (strpos($cleanHeader, $pattern) !== false) {
                    $fieldMapping[$field] = $index;
                    break;
                }
            }
        }

        // 处理所有数据行 - 添加空行过滤
        $result = [];
        foreach ($dataRows as $dataRow) {
            // 检查是否为空行
            if ($this->isEmptyRow($dataRow, $fieldMapping)) {
                continue; // 跳过空行
            }

            $goodsData = [];

            foreach ($fieldConfig as $field => $pattern) {
                if (isset($fieldMapping[$field])) {
                    $value = $dataRow[$fieldMapping[$field]] ?? '';

                    // 处理多值字段
                    if (in_array($field, $arrayFields) && !empty($value)) {
                        $goodsData[$field] = array_filter(explode(',', $value)); // 移除空数组元素
                    } else {
                        if ($field === 'goods_title') {
                            $goodsData[$field] = $this->setGoodsTitle($value);
                        } else {
                            $goodsData[$field] = trim($value);
                        }
                    }
                } else {
                    $goodsData[$field] = in_array($field, $arrayFields) ? [] : '';
                }
            }

            $result[] = $goodsData;
        }

        return $result;
    }

    /**
     * 检查是否为空行
     */
    protected function isEmptyRow($row, $fieldMapping)
    {
        // 检查关键字段是否都为空
        $keyFields = ['goods_title', 'sku', 'cate_name'];

        foreach ($keyFields as $field) {
            if (isset($fieldMapping[$field])) {
                $value = $row[$fieldMapping[$field]] ?? '';
                if (!empty(trim($value))) {
                    return false; // 只要有一个关键字段有值，就不是空行
                }
            }
        }

        return true; // 所有关键字段都为空，认为是空行
    }

    private function setGoodsTitle($value)
    {
        // 去除中文和字母/数字之间的空格
//        $value = preg_replace('/([\x{4e00}-\x{9fa5}])\s+([a-zA-Z0-9])/u', '$1$2', $value);
//        // 去除字母/数字和中文之间的空格
//        $value = preg_replace('/([a-zA-Z0-9])\s+([\x{4e00}-\x{9fa5}])/u', '$1$2', $value);

        return $value;
    }


    public function parseGoodsOptions($excelData)
    {
        $headerRow = $excelData[0];
        $dataRows = array_slice($excelData, 1);

        // 字段配置
        $fields = [
            'goods_title', 'option_title', 'product_price', 'product_model',
            'singleType', 'specs', 'volume', 'package_number', 'structure',
            'productType', 'firstCategory', 'twoCategory'
        ];

        // 创建字段映射
        $mapping = [];
        foreach ($headerRow as $index => $header) {
            $cleanHeader = trim(preg_replace('/\s+/', '', $header));
            foreach ($fields as $field) {
                if (stripos($cleanHeader, $field) !== false) {
                    $mapping[$field] = $index;
                    break;
                }
            }
        }

        // 分组处理
        $result = [];
        $currentGoodsTitle = '';

        // 存储当前商品的公共字段值
        $currentCommonFields = [
            'productType' => '',
            'firstCategory' => '',
            'twoCategory' => ''
        ];

        $specProductTypes = []; // 存储每个规格对应的productType
        $specFirstCategories = []; // 存储每个规格对应的firstCategory
        $specTwoCategories = []; // 存储每个规格对应的twoCategory

        foreach ($dataRows as $rowIndex => $row) {
            // 获取当前行的商品标题
            $rowGoodsTitle = $this->setGoodsTitle(trim($row[$mapping['goods_title']] ?? ''));

            // 如果当前行有商品标题，更新当前商品标题并重置公共字段
            if (!empty($rowGoodsTitle)) {
                $currentGoodsTitle = $rowGoodsTitle;
                // 重置所有公共字段
                $currentCommonFields = [
                    'productType' => '',
                    'firstCategory' => '',
                    'twoCategory' => ''
                ];
            }

            // 如果还没有有效的商品标题，跳过该行
            if (empty($currentGoodsTitle)) {
                continue;
            }

            // 更新公共字段的值（处理合并单元格）
            foreach (['productType', 'firstCategory', 'twoCategory'] as $commonField) {
                $rowValue = trim($row[$mapping[$commonField]] ?? '');
                if (!empty($rowValue)) {
                    $currentCommonFields[$commonField] = $rowValue;
                }
            }

            // 检查选项数据是否有效（防止空数据）
            $isValidOption = false;
            $option = [];

            foreach ($fields as $field) {
                if ($field !== 'goods_title') {
                    $value = trim($row[$mapping[$field]] ?? '');

                    // 处理 firstCategory 和 twoCategory 字段，统一转换成数组格式
                    if (in_array($field, ['firstCategory', 'twoCategory'])) {
                        if (!empty($value)) {
                            // 检查是否包含英文逗号
                            if (strpos($value, ',') !== false) {
                                // 分割字符串并去除每个元素的首尾空格
                                $value = array_map('trim', explode(',', $value));
                            } else {
                                // 没有逗号也转换成单元素数组
                                $value = [$value];
                            }
                        } else {
                            // 空值也转换成空数组
                            $value = [];
                        }
                    }

                    $option[$field] = $value;

                    // 如果至少有一个非公共字段有值，就认为是有效选项
                    if (!in_array($field, ['productType', 'firstCategory', 'twoCategory']) && !empty($value)) {
                        $isValidOption = true;
                    }
                }
            }

            // 补充公共字段的值（如果当前行为空，使用保存的公共字段值）
            foreach (['productType', 'firstCategory', 'twoCategory'] as $commonField) {
                $optionValue = $option[$commonField] ?? '';
                $currentValue = $currentCommonFields[$commonField] ?? '';

                // 判断当前选项字段是否为空（考虑数组情况）
                $isEmptyOption = false;
                if (is_array($optionValue)) {
                    $isEmptyOption = empty(array_filter($optionValue));
                } else {
                    $isEmptyOption = empty($optionValue);
                }

                if ($isEmptyOption && !empty($currentValue)) {
                    $commonValue = $currentValue;

                    // 对公共字段的值也进行统一数组格式处理
                    if (in_array($commonField, ['firstCategory', 'twoCategory'])) {
                        if (strpos($commonValue, ',') !== false) {
                            $commonValue = array_map('trim', explode(',', $commonValue));
                        } else {
                            $commonValue = [$commonValue];
                        }
                    }

                    $option[$commonField] = $commonValue;

                    // 只要有补充的公共字段值，也算有效选项
                    if (!empty($commonValue)) {
                        $isValidOption = true;
                    }
                }
            }

            // 只有有效选项才添加到结果中
            if ($isValidOption) {
                $result[$currentGoodsTitle][] = $option;

                // 提取规格名称
                $optionTitle = $option['option_title'] ?? '';
                if (!empty($optionTitle)) {
                    // 解析option_title，获取规格名称（第一个部分）
                    $specName = $this->getSpecNameFromOptionTitle($optionTitle);

                    if (!empty($specName)) {
                        // 初始化当前商品的所有映射数组
                        if (!isset($specProductTypes[$currentGoodsTitle])) {
                            $specProductTypes[$currentGoodsTitle] = [];
                            $specFirstCategories[$currentGoodsTitle] = [];
                            $specTwoCategories[$currentGoodsTitle] = [];
                        }

                        // 存储productType（如果存在）
                        if (!empty($option['productType'])) {
                            $specProductTypes[$currentGoodsTitle][$specName] = (int)$option['productType'];
                        }

                        // 存储firstCategory（如果是数组且不为空）
                        if (!empty($option['firstCategory']) && is_array($option['firstCategory'])) {
                            $specFirstCategories[$currentGoodsTitle][$specName] = $option['firstCategory'];
                        }

                        // 存储twoCategory（如果是数组且不为空）
                        if (!empty($option['twoCategory']) && is_array($option['twoCategory'])) {
                            $specTwoCategories[$currentGoodsTitle][$specName] = $option['twoCategory'];
                        }
                    }
                }
            }
        }

        // 最后再过滤一次，移除空的商品条目
        $result = array_filter($result, function($options) {
            return !empty($options);
        });

        // 返回结果和各个规格映射
        $result['spec_product_types'] = $specProductTypes;
        $result['spec_first_categories'] = $specFirstCategories;
        $result['spec_two_categories'] = $specTwoCategories;

        return $result;
    }

    /*public function parseGoodsOptions($excelData)
    {
        $headerRow = $excelData[0];
        $dataRows = array_slice($excelData, 1);

        // 字段配置
        $fields = [
            'goods_title', 'option_title', 'product_price', 'product_model',
            'singleType', 'specs', 'volume', 'package_number', 'structure', 'productType','firstCategory','twoCategory'
        ];

        // 创建字段映射
        $mapping = [];
        foreach ($headerRow as $index => $header) {
            $cleanHeader = trim(preg_replace('/\s+/', '', $header));
            foreach ($fields as $field) {
                if (stripos($cleanHeader, $field) !== false) {
                    $mapping[$field] = $index;
                    break;
                }
            }
        }

        // 分组处理
        $result = [];
        $currentGoodsTitle = '';
        $currentProductType = ''; // 当前商品标题对应的productType
        $specProductTypes = []; // 存储每个规格对应的productType

        foreach ($dataRows as $rowIndex => $row) {
            // 获取当前行的商品标题
            $rowGoodsTitle = $this->setGoodsTitle(trim($row[$mapping['goods_title']] ?? ''));

            // 如果当前行有商品标题，更新当前商品标题和productType
            if (!empty($rowGoodsTitle)) {
                $currentGoodsTitle = $rowGoodsTitle;
                // 重置当前productType，准备获取新的值
                $currentProductType = '';
            }

            // 如果还没有有效的商品标题，跳过该行
            if (empty($currentGoodsTitle)) {
                continue;
            }

            // 获取当前行的productType
            $rowProductType = trim($row[$mapping['productType']] ?? '');

            // 如果当前行有productType值，更新当前productType
            if (!empty($rowProductType)) {
                $currentProductType = $rowProductType;
            }

            // 检查选项数据是否有效（防止空数据）
            $isValidOption = false;
            $option = [];

            foreach ($fields as $field) {
                if ($field !== 'goods_title') {
                    $value = trim($row[$mapping[$field]] ?? '');
                    $option[$field] = $value;

                    // 如果至少有一个字段有值，就认为是有效选项
                    if (!empty($value)) {
                        $isValidOption = true;
                    }
                }
            }

            // 只有有效选项才添加到结果中
            if ($isValidOption) {
                // 如果当前行没有productType值，但当前商品有productType值，则使用当前商品的productType
                if (empty($option['productType']) && !empty($currentProductType)) {
                    $option['productType'] = $currentProductType;
                }

                $result[$currentGoodsTitle][] = $option;

                // 提取规格名称并记录对应的productType
                $optionTitle = $option['option_title'] ?? '';
                $productType = $option['productType'] ?? '';

                if (!empty($optionTitle) && !empty($productType)) {
                    // 解析option_title，获取规格名称（第一个部分）
                    $specName = $this->getSpecNameFromOptionTitle($optionTitle);

                    if (!empty($specName)) {
                        // 存储规格对应的productType（去重）
                        if (!isset($specProductTypes[$currentGoodsTitle])) {
                            $specProductTypes[$currentGoodsTitle] = [];
                        }

                        $specProductTypes[$currentGoodsTitle][$specName] = (int)$productType;
                    }
                }
            }
        }

        // 最后再过滤一次，移除空的商品条目
        $result = array_filter($result, function($options) {
            return !empty($options);
        });

        // 返回结果和规格对应的productType映射
        $result['spec_product_types'] = $specProductTypes;

        return $result;
    }*/

    /**
     * 从option_title中提取规格名称（第一个部分）
     */
    private function getSpecNameFromOptionTitle($optionTitle)
    {
        if (empty($optionTitle)) {
            return '';
        }

        // 使用下划线分割option_title
        $parts = explode('_', $optionTitle);

        if (count($parts) > 0) {
            $specName = trim($parts[0]);

            return $specName;
        }

        return $optionTitle;
    }

    /**
     * 获取去重后的规格productType映射
     */
    public function getUniqueSpecProductTypes($parsedData)
    {
        $uniqueMappings = [];

        foreach ($parsedData['spec_product_types'] ?? [] as $goodsTitle => $specTypes) {
            $uniqueMappings[$goodsTitle] = $specTypes;
        }

        return $uniqueMappings;
    }

}