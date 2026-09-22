<?php


namespace app\frontend\modules\project\services;

use app\common\models\GoodsOption;

class GoodsComparator
{
    private $all_children_model_type = [];
    private $goodsGroups = [];

    private $goodsOriginList;

    public function compareGoods($goodsList)
    {

        $this->goodsOriginList = $goodsList;
        $this->all_children_model_type = [];

        // 第一步：过滤需要排除的商品
        $filteredGoodsList = array_filter($goodsList, function ($goodsData) {

            // 排除 productType == 5 且 children 长度等于1的商品
            return !($goodsData['data']['productType'] == 5 || count($goodsData['children']) == 1 || count($goodsData['children']) == 0);
        });


        
        // 重新索引数组
        $filteredGoodsList = array_values($filteredGoodsList);

        // 第二步：收集所有商品的子组件信息
        foreach ($filteredGoodsList as $goodsData) {


            $goodsInfo = $this->getAllChildrenModelType($goodsData);
            $goodsInfo['scale'] = [
                'x' => $goodsData['scale']['x'],
                'z' => $goodsData['scale']['z']
            ];
            $this->all_children_model_type[$goodsData['goods_uuid']] = $goodsInfo;
        }


        // 第三步：分组相同的商品
        $this->groupSimilarGoods($filteredGoodsList);

        // 第四步：统计结果
        return $this->formatResult();
    }

    private function getAllChildrenModelType($goodsData)
    {
        $children_model_type = [];
        $child_scale = [];

        foreach ($goodsData['children'] as $key => $value) {
            $children_model_type[] = $value['modelType'];
            $child_scale[] = [
                'x' => $value['scale']['x'],
                'z' => $value['scale']['z']
            ];
        }


        return [
            'children_model_type' => $children_model_type,
            'child_scale' => $child_scale,
            'goods_data' => $goodsData // 保存原始数据用于后续比较
        ];
    }


    private function groupSimilarGoods($goodsList)
    {
        $this->goodsGroups = [];

        // 创建原数据索引映射（使用 goods_uuid 作为键）
        $indexMap = [];
        foreach ($this->goodsOriginList as $index => $goods) {
            if (isset($goods['goods_uuid'])) {
                $indexMap[$goods['goods_uuid']] = $index;
            }
        }

        foreach ($goodsList as $index => $currentGoods) {
            $currentUuid = $currentGoods['goods_uuid'];
            $currentInfo = $this->all_children_model_type[$currentUuid];
            $grouped = false;

            // 检查是否已经存在于某个分组中
            foreach ($this->goodsGroups as &$group) {
                $firstGoodsUuid = $group['goods'][0];
                $firstInfo = $this->all_children_model_type[$firstGoodsUuid];

                if ($this->areGoodsIdentical($currentInfo, $firstInfo, $currentGoods, $this->all_children_model_type[$firstGoodsUuid]['goods_data'])) {
                    // 添加商品UUID和原索引
                    $group['goods'][] = $currentUuid;
                    $group['goods_indices'][] = $indexMap[$currentUuid] ?? null; // 记录原索引
                    $group['count']++;
                    $grouped = true;
                    break;
                }
            }

            if (!$grouped) {
                // 创建新的分组，同时记录原索引
                $this->goodsGroups[] = [
                    'goods' => [$currentUuid],
                    'goods_indices' => [$indexMap[$currentUuid] ?? null], // 记录原索引
                    'count' => 1,
                    'sample_data' => $currentGoods
                ];
            }
        }
    }

    // private function groupSimilarGoods($goodsList)
    // {
    //     $this->goodsGroups = [];

    //     foreach ($goodsList as $index => $currentGoods) {
    //         $currentUuid = $currentGoods['goods_uuid'];

    //         $currentInfo = $this->all_children_model_type[$currentUuid];

    //         $grouped = false;

    //         // 检查是否已经存在于某个分组中

    //         foreach ($this->goodsGroups as &$group) {
    //             $firstGoodsUuid = $group['goods'][0];

    //             $firstInfo = $this->all_children_model_type[$firstGoodsUuid];

    //             if ($this->areGoodsIdentical($currentInfo, $firstInfo, $currentGoods, $this->all_children_model_type[$firstGoodsUuid]['goods_data'])) {
    //                 $group['goods'][] = $currentUuid;
    //                 $group['count']++;
    //                 $grouped = true;
    //                 break;
    //             }
    //         }

    //         if (!$grouped) {
    //             // 创建新的分组
    //             $this->goodsGroups[] = [
    //                 'goods' => [$currentUuid],
    //                 'count' => 1,
    //                 'sample_data' => $currentGoods
    //             ];
    //         }
    //     }
    // }

    private function areGoodsIdentical($info1, $info2, $goods1, $goods2)
    {
        //检查规格id 是否相同
        if ($goods1['data']['id'] != $goods2['data']['id']) {
            return false;
        }
        // 检查 scale 的 x 和 z 是否相同
        if ($info1['scale']['x'] != $info2['scale']['x'] || $info1['scale']['z'] != $info2['scale']['z']) {
            return false;
        }

        // 条件1：检查 children_model_type 数组是否完全相同（包括顺序）

        if (!$this->compareModelTypes($info1['children_model_type'], $info2['children_model_type'])) {
            return false;
        }

        // 条件2：检查 child_scale 的 x 和 z 是否相同
        if (!$this->compareScales($info1['child_scale'], $info2['child_scale'])) {
            return false;
        }



        // 条件3：检查 model_data 中的 default_color id 是否相同
        if (!$this->compareModelColors($goods1, $goods2)) {
            return false;
        }

        // 新增条件4：定制商品备注比较（去除空格后）
        if (!$this->compareCustomizedRemark($goods1, $goods2)) {
            return false;
        }

        return true;
    }


    /**
     * 比较两个商品的定制备注（仅当两者均为定制商品时）
     * @param array $goods1
     * @param array $goods2
     * @return bool
     */
    private function compareCustomizedRemark($goods1, $goods2)
    {
       
        $isCustomized1 = isset($goods1['customized']['is_customized']) && $goods1['customized']['is_customized'] === true;
        $isCustomized2 = isset($goods2['customized']['is_customized']) && $goods2['customized']['is_customized'] === true;

        // 一个定制，另一个非定制 -> 不相同
        if ($isCustomized1 !== $isCustomized2) {
            return false;
        }

        // 两个都不是定制商品 -> 视为相同（通过）
        // if (!$isCustomized1 && !$isCustomized2) {
        //     return true;
        // }

        // 两个都是定制商品：比较去除空格后的 customized_remark
        $remark1 = isset($goods1['customized']['customized_remark']) ? $goods1['customized']['customized_remark'] : '';
        $remark2 = isset($goods2['customized']['customized_remark']) ? $goods2['customized']['customized_remark'] : '';

        // 去除所有空白字符（包括空格、制表符、换行等）
        $cleanRemark1 = preg_replace('/\s+/u', '', $remark1);
        $cleanRemark2 = preg_replace('/\s+/u', '', $remark2);

        return $cleanRemark1 === $cleanRemark2 && $goods1['customized']['size']['x'] === $goods2['customized']['size']['x'] && $goods1['customized']['size']['y'] === $goods2['customized']['size']['y'] && $goods1['customized']['size']['z'] === $goods2['customized']['size']['z'];
    }

    private function compareModelTypes($typeArray1, $typeArray2)
    {
        // 严格比较数组，包括顺序
        return $typeArray1 === $typeArray2;
    }

    private function compareScales($scaleArray1, $scaleArray2)
    {
        if (count($scaleArray1) !== count($scaleArray2)) {
            return false;
        }

        foreach ($scaleArray1 as $index => $scale1) {
            $scale2 = $scaleArray2[$index];

            // 比较 x 和 z 值
            if ($scale1['x'] !== $scale2['x'] || $scale1['z'] !== $scale2['z']) {
                return false;
            }
        }

        return true;
    }

    private function compareModelColors($goods1, $goods2)
    {

        // 获取所有子组件的 modelType
        $modelTypes = [];
        foreach ($goods1['children'] as $child) {
            $modelTypes[] = $child['modelType'];
        }

        // 对每个 modelType 比较颜色
        foreach ($modelTypes as $modelType) {
            $modelData1 = $this->findModelChildrenDataByType($goods1, $modelType);

            $modelData2 = $this->findModelChildrenDataByType($goods2, $modelType);

            if (!$this->compareModelDataColors($modelData1, $modelData2)) {
                return false;
            }
        }




        return true;
    }

    public function findModelChildrenDataByType($goodsData, $modelType)
    {
        foreach ($goodsData['children'] as $modelData) {
            foreach ($modelData['model_chilren_data']  as $item) {
                if ($item['modelType'] == $modelType) {
                    return $item;
                }
            }
        }
        return null;
    }

    public function findModelChildrenDataOptionIds($childrenData)
    {
        $optionData = [];
        foreach ($childrenData as $modelData) {
            foreach ($modelData['model_chilren_data']  as $item) {
                if ($item['modelType'] == $modelData['modelType']) {
                    $optionItem['goods_id'] = $item['goods_id'];
                    $optionItem['option_id'] = $item['id'];
                    $optionData[] = $optionItem;
                }
            }
        }
        return $optionData;
    }

    private function compareModelDataColors($modelData1, $modelData2)
    {
        if (!$modelData1 || !$modelData2) {
            return false;
        }

        $colorIds1 = $this->extractColorIds($modelData1['model_data']);

        $colorIds2 = $this->extractColorIds($modelData2['model_data']);


        // 比较颜色ID数组
        return $colorIds1 === $colorIds2;
    }

    private function extractColorIds($modelDataArray)
    {
        $colorIds = [];

        foreach ($modelDataArray as $component) {

            if (isset($component['curColor']['id']) && $component['visible'] == 0 && $component['changeLock'] == 1) {
                $colorIds[] = $component['curColor']['id'];
            }
        }

        //sort($colorIds); // 排序以确保比较不受顺序影响
        return $colorIds;
    }

    private function formatResult()
    {

        return $this->goodsGroups;
        // $result = [];

        // foreach ($this->goodsGroups as $group) {
        //     $sampleGoods = $group['sample_data'];

        //     $result[] = [
        //         'goods_uuid' => $sampleGoods['goods_uuid'],
        //         'title' => $sampleGoods['data']['title'],
        //         'count' => $group['count'],
        //         'children_model_type' => $this->all_children_model_type[$sampleGoods['goods_uuid']]['children_model_type'],
        //         'sample_data' => [
        //             'position' => $sampleGoods['position'],
        //             'rotation' => $sampleGoods['rotation'],
        //             'price' => $sampleGoods['data']['price']
        //         ]
        //     ];
        // }

        // return $result;
    }


    public function getMirroredImages($option_id, $scale)
    {
        $goods_option = GoodsOption::find($option_id);
        $isMirrored = ($scale['x'] * $scale['z'] < 0);
        if ($isMirrored) {
            // 有镜像处理图片
            if (!$goods_option->mirror_thumb) {
                $main_thumb = @unserialize($goods_option->thumb_url);
                $upload_res =  $this->mirrorImage(yz_tomedia($main_thumb['main_thumb'] ?: $goods_option->thumb), $scale);

                $goods_option->mirror_thumb = $upload_res['webp_thumb_absolute_path'];
                $goods_option->mirror_thumb_high = $upload_res['high_absolute_path'];

                $goods_option->save();
            }
        }
    }

    /**
     * 镜像图片处理函数
     * 
     * @param string $imageUrl 原图片URL
     * @param array $scale 缩放参数
     * @return string 处理后的图片URL
     */
    public function mirrorImage($imageUrl, $scale)
    {
        // 如果图片URL为空，直接返回
        if (empty($imageUrl)) {
            return '';
        }

        try {
            // 判断是X轴镜像还是Z轴镜像
            if ($scale['x'] < 0) {
                // X轴镜像（水平翻转）
                return $this->downloadAndMirrorImage($imageUrl, 'horizontal');
            } elseif ($scale['z'] < 0) {
                // Z轴镜像（垂直翻转）
                return $this->downloadAndMirrorImage($imageUrl, 'vertical');
            }
        } catch (\Exception $e) {
            // 记录错误日志
            \Log::error('镜像图片处理失败: ' . $e->getMessage(), [
                'imageUrl' => $imageUrl,
                'scale' => $scale
            ]);
            return $imageUrl;
        }

        return $imageUrl;
    }

    /**
     * 下载图片并镜像处理
     */
    private function downloadAndMirrorImage($imageUrl, $direction)
    {
        // 创建临时目录
        $tempDir = storage_path('app/temp/images/');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // 创建镜像图片存储目录
        $mirroredDir = storage_path('app/public/mirrored_images/');
        if (!file_exists($mirroredDir)) {
            mkdir($mirroredDir, 0755, true);
        }

        // 生成唯一文件名
        $urlHash = md5($imageUrl . '_' . $direction);
        $originalExtension = pathinfo($imageUrl, PATHINFO_EXTENSION);
        $originalExtension = $originalExtension ?: 'jpg';

        // 临时文件路径
        $tempFilePath = $tempDir . $urlHash . '_original.' . $originalExtension;
        // 镜像文件路径
        $mirroredFileName = $urlHash . '_' . $direction . '.' . $originalExtension;
        $mirroredFilePath = $mirroredDir . $mirroredFileName;
        // 访问路径
        $mirroredUrlPath = 'storage/mirrored_images/' . $mirroredFileName;




        // 下载图片到临时文件
        if (!$this->downloadImage($imageUrl, $tempFilePath)) {
            \Log::error('图片下载失败: ' . $imageUrl);
            return $imageUrl;
        }

        // 处理镜像
        $result = $this->processImageMirror($tempFilePath, $mirroredFilePath, $direction);

        // 清理临时文件
        if (file_exists($tempFilePath)) {
            unlink($tempFilePath);
        }


        if ($result) {
            $upload_res =  uploadOssV2($mirroredFilePath, uniqid() . ".png", 1, 'thumb');
        }

        return $result ? $upload_res : [];
    }

    /**
     * 下载图片到本地
     */
    private function downloadImage($url, $savePath)
    {
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->get($url, [
                'timeout' => 30,
                'verify' => false, // 如果是自签名证书可能需要关闭验证
            ]);

            if ($response->getStatusCode() == 200) {
                file_put_contents($savePath, $response->getBody());
                return file_exists($savePath);
            }
        } catch (\Exception $e) {
            \Log::error('图片下载异常: ' . $e->getMessage(), ['url' => $url]);

            // 备用方案：使用file_get_contents
            try {
                $content = @file_get_contents($url);
                if ($content !== false) {
                    file_put_contents($savePath, $content);
                    return file_exists($savePath);
                }
            } catch (\Exception $e2) {
                \Log::error('备用下载方案也失败: ' . $e2->getMessage());
            }
        }

        return false;
    }

    /**
     * 处理图片镜像（支持WebP）
     */
    private function processImageMirror($sourcePath, $destPath, $direction)
    {
        if (!file_exists($sourcePath)) {
            return false;
        }

        try {
            // 检测图片类型
            $imageType = exif_imagetype($sourcePath);
            if (!$imageType) {
                // 尝试通过扩展名判断
                $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
                switch ($extension) {
                    case 'jpg':
                    case 'jpeg':
                        $imageType = IMAGETYPE_JPEG;
                        break;
                    case 'png':
                        $imageType = IMAGETYPE_PNG;
                        break;
                    case 'gif':
                        $imageType = IMAGETYPE_GIF;
                        break;
                    case 'webp':
                        $imageType = IMAGETYPE_WEBP;
                        break;
                    default:
                        return false;
                }
            }

            // 创建图片资源
            switch ($imageType) {
                case IMAGETYPE_JPEG:
                    $image = imagecreatefromjpeg($sourcePath);
                    break;
                case IMAGETYPE_PNG:
                    $image = imagecreatefrompng($sourcePath);
                    break;
                case IMAGETYPE_GIF:
                    $image = imagecreatefromgif($sourcePath);
                    break;
                case IMAGETYPE_WEBP:
                    // 检查GD库是否支持WebP
                    if (!function_exists('imagecreatefromwebp')) {
                        \Log::error('GD库不支持WebP格式: ' . $sourcePath);
                        return false;
                    }
                    $image = imagecreatefromwebp($sourcePath);
                    break;
                default:
                    return false;
            }

            if (!$image) {
                \Log::error('创建图片资源失败: ' . $sourcePath);
                return false;
            }

            $width = imagesx($image);
            $height = imagesy($image);
            $mirrored = imagecreatetruecolor($width, $height);

            // 处理透明背景
            if ($imageType == IMAGETYPE_PNG || $imageType == IMAGETYPE_GIF || $imageType == IMAGETYPE_WEBP) {
                imagealphablending($mirrored, false);
                imagesavealpha($mirrored, true);
                $transparent = imagecolorallocatealpha($mirrored, 0, 0, 0, 127);
                imagefill($mirrored, 0, 0, $transparent);
            } else {
                // JPEG使用白色背景
                $white = imagecolorallocate($mirrored, 255, 255, 255);
                imagefill($mirrored, 0, 0, $white);
            }

            // 执行镜像
            if ($direction === 'horizontal') {
                // 水平镜像
                for ($x = 0; $x < $width; $x++) {
                    imagecopy($mirrored, $image, $width - $x - 1, 0, $x, 0, 1, $height);
                }
            } else {
                // 垂直镜像
                for ($y = 0; $y < $height; $y++) {
                    imagecopy($mirrored, $image, 0, $height - $y - 1, 0, $y, $width, 1);
                }
            }

            // 保存图片
            switch ($imageType) {
                case IMAGETYPE_JPEG:
                    imagejpeg($mirrored, $destPath, 90);
                    break;
                case IMAGETYPE_PNG:
                    imagepng($mirrored, $destPath, 9);
                    break;
                case IMAGETYPE_GIF:
                    imagegif($mirrored, $destPath);
                    break;
                case IMAGETYPE_WEBP:
                    // WebP质量设置，范围0-100
                    if (!function_exists('imagewebp')) {
                        \Log::error('GD库不支持保存WebP格式: ' . $destPath);
                        imagedestroy($image);
                        imagedestroy($mirrored);
                        return false;
                    }
                    imagewebp($mirrored, $destPath, 90); // 90%质量
                    break;
            }

            imagedestroy($image);
            imagedestroy($mirrored);

            return file_exists($destPath);
        } catch (\Exception $e) {
            \Log::error('图片镜像处理失败: ' . $e->getMessage(), [
                'source' => $sourcePath,
                'dest' => $destPath
            ]);
            return false;
        }
    }
}
