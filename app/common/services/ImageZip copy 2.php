<?php

namespace app\common\services;


use app\common\exceptions\ShopException;

class ImageZip
{
    /**
     * 压缩/缩略
     * @param $srcFile
     * @param $number
     * @param $type
     * @return bool
     */
    public static function makeThumb($srcFile, $number, $type)
    {

        //type 1 压缩 2 缩略
        $img = getimagesize($srcFile);
        if (!is_file($srcFile) || $img == false) {
            return false;
        }
        list($src_width, $src_height, $src_type) = $img; //原图宽度、高度
        $memory_limit = trim(ini_get('memory_limit'), 'M');
        $img_memory = $src_width * $src_height * 3 * 1.7;
        if ($img_memory > $memory_limit * 1024 * 1024) { //imagecreatetruecolor方法生成图片资源时会占用大量的服务器内存，所以在上传大图、长图时不能使用
            return false;
        }

        if ($type == 2) {
            if ($src_width < $number) {
                $width = $src_width;
                $number = $src_width;
            } else {
                $width = $number;
            }
            $height = $src_height * $number / $src_width;
        } else {
            $width = $src_width;
            $height = $src_height;
        }
        switch ($src_type) {
            case 1:
                $image_type = 'gif';
                break;
            case 2:
                $image_type = 'jpeg';
                break;
            case 3:
                $image_type = 'png';
                break;
            case 15:
                $image_type = 'wbmp';
                break;
            default:
                return false;
        }

        $image_canvas = imagecreatetruecolor($width, $height); //创建画布
        $bg = imagecolorallocatealpha($image_canvas, 255, 255, 255, 127);
        imagefill($image_canvas, 0, 0, $bg);
        $imagecreatefromfunc = 'imagecreatefrom' . $image_type;
        $image_resources = $imagecreatefromfunc($srcFile);
        imagecopyresampled($image_canvas, $image_resources, 0, 0, 0, 0, $width, $height, $src_width, $src_height); //缩放图片（高精度）
        if ($type == 2) {
            $imagefunc = 'image' . $image_type;
            $imagefunc($image_canvas, $srcFile);
        } else {
            imagejpeg($image_canvas, $srcFile, $number);
        }
        imagedestroy($image_canvas);
        imagedestroy($image_resources);
        return true;
    }





    /* public static function makeThumbWebp($srcFile, $mode = 'main')
    {
        // 模式配置
        $config = [
            'thumb' => ['width' => 300, 'quality' =>85, 'webp' => true],
            'medium_thumb'=>['width' => 512, 'quality' =>85, 'webp' => true],
            'goodsmain' => ['width' => 748, 'quality' => 85, 'webp' => true],
            'main' => ['width' => 748, 'quality' => 85, 'webp' => false],
            'detail' => ['width' => 1200, 'quality' => 100, 'webp' => false],
            'original'=> ['width' => 3000, 'quality' => 95, 'webp' => false],
        ];

        if (!isset($config[$mode])) return false;

        $img = getimagesize($srcFile);
        if (!is_file($srcFile) || $img == false) return false;

        list($src_width, $src_height, $src_type) = $img;
        $memory_limit = trim(ini_get('memory_limit'), 'M');
        $img_memory = $src_width * $src_height * 3 * 1.7;
        if ($img_memory > $memory_limit * 1024 * 1024) return false;

        $target_width = $config[$mode]['width'];
        $quality = $config[$mode]['quality'];
        $generate_webp = $config[$mode]['webp'];

        // 缩放尺寸计算
        if ($src_width <= $target_width) {
            $width = $src_width;
            $height = $src_height;
        } else {
            $width = $target_width;
            $height = intval($src_height * $width / $src_width);
        }

        // 类型映射
        $type_map = [1 => 'gif', 2 => 'jpeg', 3 => 'png', 15 => 'wbmp'];
        if (!isset($type_map[$src_type])) return false;

        $image_type = $type_map[$src_type];
        $imagecreatefromfunc = 'imagecreatefrom' . $image_type;
        if (!function_exists($imagecreatefromfunc)) return false;

        $image_canvas = imagecreatetruecolor($width, $height);
        imagealphablending($image_canvas, false);
        imagesavealpha($image_canvas, true);
        $bg = imagecolorallocatealpha($image_canvas, 255, 255, 255, 127);
        imagefill($image_canvas, 0, 0, $bg);

        $image_resources = $imagecreatefromfunc($srcFile);
        imagecopyresampled($image_canvas, $image_resources, 0, 0, 0, 0, $width, $height, $src_width, $src_height);

        // 修改这里 - 不覆盖原文件，创建新文件
        $jpegPath = preg_replace('/\.(jpg|jpeg|png|gif|bmp)$/i', '', $srcFile) . "_$mode.jpg";
        imagejpeg($image_canvas, $jpegPath, $quality);
        // 生成 WebP（仅主图）
        $webpPath = null;
        if ($generate_webp && function_exists('imagewebp')) {
            $webpPath = preg_replace('/\.(jpg|jpeg|png|gif|bmp)$/i', '', $srcFile) . "_$mode.webp";
            imagewebp($image_canvas, $webpPath, $quality);
        }

        imagedestroy($image_canvas);
        imagedestroy($image_resources);

        return $webpPath ?: $jpegPath; // 返回 WebP 路径（如果生成了），否则 true
    }*/



    public static function makeThumbWebp($srcFile, $mode = 'main', $uniqueId = '')
    {
        // 生成唯一标识符，如果没有提供
        if (empty($uniqueId)) {
            $uniqueId = uniqid();
        }

        // 模式配置 - 增加最小压缩宽度参数
        $config = [
            'thumb' => ['width' => 300, 'quality' => 95, 'webp' => true, 'min_compress_width' => 300],  // 缩略图总是压缩
            'medium_thumb' => ['width' => 700, 'quality' => 100, 'webp' => true, 'min_compress_width' => 256],  // 中等缩略图总是压缩
            //'high_thumb' => ['width' => 1024, 'quality' => 100, 'webp' => true, 'min_compress_width' => 256],  // 中等缩略图总是压缩
            'original' => ['width' => 3000, 'quality' => 100, 'webp' => false, 'min_compress_width' => 800],  // 小于800不压缩
            'goodsmain' => ['width' => 748, 'quality' => 95, 'webp' => true, 'min_compress_width' => 800],  // 小于800不压缩
            'main' => ['width' => 748, 'quality' => 95, 'webp' => false, 'min_compress_width' => 800],  // 小于800不压缩
            'detail' => ['width' => 1200, 'quality' => 95, 'webp' => false, 'min_compress_width' => 800],  // 小于800不压缩
            
        ];

        if (!isset($config[$mode])) {
            \Log::error("Invalid mode: {$mode}");
            return false;
        }

        if (!is_file($srcFile)) {
            \Log::error("Source file not found: {$srcFile}");
            return false;
        }

        $img = getimagesize($srcFile);
        if ($img == false) {
            \Log::error("Cannot get image size: {$srcFile}");
            return false;
        }

        list($src_width, $src_height, $src_type) = $img;

        // 检查内存限制
        $memory_limit = self::getMemoryLimit();
        $img_memory = $src_width * $src_height * 3 * 1.7;

        if ($img_memory > $memory_limit * 1024 * 1024) {
            \Log::error("Image memory requirement exceeds limit: {$img_memory} > {$memory_limit}");
            return false;
        }

        $target_width = $config[$mode]['width'];
        $quality = $config[$mode]['quality'];
        $generate_webp = $config[$mode]['webp'];
        $min_compress_width = $config[$mode]['min_compress_width'];

        // 创建临时目录
        $tempDir = sys_get_temp_dir() . '/image_processing/';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        // 检查是否需要压缩：如果原图宽度小于最小压缩宽度，则使用原图尺寸和质量100
        $should_compress = true;
        $final_quality = $quality;

        if ($src_width < $min_compress_width) {
            $should_compress = false;
            $final_quality = 100; // 使用最佳质量
            \Log::info("Image width {$src_width}px is less than minimum compress width {$min_compress_width}px for mode {$mode}, using original size with quality 100");
        }

        // 计算最终尺寸
        if ($mode === 'detail') {
            if (!$should_compress || $src_width < $target_width) {
                // 不压缩或宽度小于目标宽度，使用原图
                $width = $src_width;
                $height = $src_height;
            } else {
                // 需要压缩且宽度大于等于目标宽度
                $width = $target_width;
                $height = intval($src_height * $width / $src_width);
            }
        } else {
            if (!$should_compress || $src_width <= $target_width) {
                // 不压缩或宽度小于等于目标宽度，使用原图
                $width = $src_width;
                $height = $src_height;
            } else {
                // 需要压缩且宽度大于目标宽度
                $width = $target_width;
                $height = intval($src_height * $width / $src_width);
            }
        }

        // 如果不需要压缩且尺寸与原图相同，直接处理质量即可
        if (!$should_compress && $width == $src_width && $height == $src_height) {
            return self::processWithoutCompression($srcFile, $width, $height, $src_type, $mode, $uniqueId, $final_quality, $generate_webp, $tempDir);
        }

        // 类型映射
        $type_map = [1 => 'gif', 2 => 'jpeg', 3 => 'png', 15 => 'wbmp', 18 => 'webp'];

        if (!isset($type_map[$src_type])) {
            \Log::error("Unsupported image type: {$src_type}");
            return false;
        }

        $image_type = $type_map[$src_type];
        $imagecreatefromfunc = 'imagecreatefrom' . $image_type;

        if (!function_exists($imagecreatefromfunc)) {
            \Log::error("Function not exists: {$imagecreatefromfunc}");
            return false;
        }

        try {
            // 创建画布
            $image_canvas = imagecreatetruecolor($width, $height);
            if (!$image_canvas) {
                throw new \Exception("Failed to create true color image");
            }

            imagealphablending($image_canvas, false);
            imagesavealpha($image_canvas, true);
            $bg = imagecolorallocatealpha($image_canvas, 255, 255, 255, 127);
            imagefill($image_canvas, 0, 0, $bg);

            // 读取源图
            $image_resources = $imagecreatefromfunc($srcFile);
            if (!$image_resources) {
                throw new \Exception("Failed to create image from source");
            }

            // 处理图像
            if ($width == $src_width && $height == $src_height) {
                // 尺寸相同，直接拷贝
                imagecopy($image_canvas, $image_resources, 0, 0, 0, 0, $width, $height);
            } else {
                // 需要缩放
                imagecopyresampled($image_canvas, $image_resources, 0, 0, 0, 0, $width, $height, $src_width, $src_height);
            }

            // 如果要导出为 JPG，需要先把透明背景合成到白底，否则透明会变黑
            $flatten_canvas = imagecreatetruecolor($width, $height);
            $white = imagecolorallocate($flatten_canvas, 255, 255, 255);
            imagefill($flatten_canvas, 0, 0, $white);

            // 将带 alpha 的画布拷贝到白底上
            imagecopy($flatten_canvas, $image_canvas, 0, 0, 0, 0, $width, $height);

            // 输出 JPG 到 $jpegPath
            $jpegPath = $tempDir . basename($srcFile, '.' . pathinfo($srcFile, PATHINFO_EXTENSION)) . "_{$mode}_{$uniqueId}.jpg";
            imagejpeg($flatten_canvas, $jpegPath, $final_quality);
            // 清理
            imagedestroy($flatten_canvas);

            // 生成 WebP
            $webpPath = null;
            if ($generate_webp && function_exists('imagewebp')) {
                $webpPath = $tempDir . basename($srcFile, '.' . pathinfo($srcFile, PATHINFO_EXTENSION)) . "_{$mode}_{$uniqueId}.webp";

                if (!imagewebp($image_canvas, $webpPath, $final_quality)) {
                    \Log::warning("Failed to save WebP image, falling back to JPEG");
                    $webpPath = null;
                }
            }

            // 清理资源
            imagedestroy($image_canvas);
            imagedestroy($image_resources);

            return $webpPath ?: $jpegPath;
        } catch (\Exception $e) {
            \Log::error("Image processing failed: " . $e->getMessage());

            // 确保资源被释放
            if (isset($image_canvas)) {
                imagedestroy($image_canvas);
            }
            if (isset($image_resources)) {
                imagedestroy($image_resources);
            }

            return false;
        }
    }

    /**
     * 不压缩处理 - 直接转换格式和质量
     */
    private static function processWithoutCompression($srcFile, $width, $height, $src_type, $mode, $uniqueId, $quality, $generate_webp, $tempDir)
    {
        $type_map = [1 => 'gif', 2 => 'jpeg', 3 => 'png', 15 => 'wbmp', 18 => 'webp'];
        $image_type = $type_map[$src_type];
        $imagecreatefromfunc = 'imagecreatefrom' . $image_type;

        if (!function_exists($imagecreatefromfunc)) {
            \Log::error("Function not exists: {$imagecreatefromfunc}");
            return false;
        }

        try {
            // 读取源图
            $image_resources = $imagecreatefromfunc($srcFile);
            if (!$image_resources) {
                throw new \Exception("Failed to create image from source");
            }

            // 创建相同尺寸的画布
            $image_canvas = imagecreatetruecolor($width, $height);
            if (!$image_canvas) {
                throw new \Exception("Failed to create true color image");
            }

            imagealphablending($image_canvas, false);
            imagesavealpha($image_canvas, true);
            $bg = imagecolorallocatealpha($image_canvas, 255, 255, 255, 127);
            imagefill($image_canvas, 0, 0, $bg);

            // 直接拷贝原图
            imagecopy($image_canvas, $image_resources, 0, 0, 0, 0, $width, $height);

            // 处理透明背景为白色背景
            $flatten_canvas = imagecreatetruecolor($width, $height);
            $white = imagecolorallocate($flatten_canvas, 255, 255, 255);
            imagefill($flatten_canvas, 0, 0, $white);
            imagecopy($flatten_canvas, $image_canvas, 0, 0, 0, 0, $width, $height);

            // 输出 JPG
            $jpegPath = $tempDir . basename($srcFile, '.' . pathinfo($srcFile, PATHINFO_EXTENSION)) . "_{$mode}_{$uniqueId}.jpg";
            imagejpeg($flatten_canvas, $jpegPath, $quality);
            imagedestroy($flatten_canvas);

            // 生成 WebP
            $webpPath = null;
            if ($generate_webp && function_exists('imagewebp')) {
                $webpPath = $tempDir . basename($srcFile, '.' . pathinfo($srcFile, PATHINFO_EXTENSION)) . "_{$mode}_{$uniqueId}.webp";

                if (!imagewebp($image_canvas, $webpPath, $quality)) {
                    \Log::warning("Failed to save WebP image, falling back to JPEG");
                    $webpPath = null;
                }
            }

            // 清理资源
            imagedestroy($image_canvas);
            imagedestroy($image_resources);

            return $webpPath ?: $jpegPath;
        } catch (\Exception $e) {
            \Log::error("Process without compression failed: " . $e->getMessage());
            return false;
        }
    }

    // 辅助方法：获取内存限制
    private static function getMemoryLimit()
    {
        $memory_limit = ini_get('memory_limit');
        if (preg_match('/^(\d+)(.)$/', $memory_limit, $matches)) {
            if ($matches[2] == 'M') {
                return $matches[1];
            } elseif ($matches[2] == 'G') {
                return $matches[1] * 1024;
            } elseif ($matches[2] == 'K') {
                return $matches[1] / 1024;
            }
        }
        return 128; // 默认128M
    }









    /* public static function makeThumb($srcFile, $number, $type) {
         // 1. 验证文件有效性
         $img = @getimagesize($srcFile);
         if (!is_file($srcFile) || $img === false) {
             return false;
         }

         // 2. 获取原图信息
         list($src_width, $src_height, $src_type) = $img;
         $memory_limit = (int) trim(ini_get('memory_limit'), 'M');
         $img_memory = $src_width * $src_height * 4 / 1024 / 1024; // 更精确的内存计算（MB单位）

         // 3. 内存检查（保留20%缓冲空间）
         if ($img_memory > $memory_limit * 0.8) {
             return false;
         }

         // 4. 计算目标尺寸
         if ($type == 2) { // 缩略模式
             $width = min($src_width, $number);
             $height = (int) round($src_height * $width / $src_width);
         } else { // 压缩模式
             $width = $src_width;
             $height = $src_height;
         }

         // 5. 图像类型处理（优先WebP）
         $output_webp = function_exists('imagewebp');
         switch ($src_type) {
             case IMAGETYPE_GIF:
                 $image_type = 'gif';
                 break;
             case IMAGETYPE_JPEG:
                 $image_type = $output_webp ? 'webp' : 'jpeg';
                 break;
             case IMAGETYPE_PNG:
                 $image_type = $output_webp ? 'webp' : 'png';
                 break;
             case IMAGETYPE_WBMP:
                 $image_type = 'wbmp';
                 break;
             default:
                 return false;
         }

         // 6. 创建画布并设置高精度插值
         $image_canvas = imagecreatetruecolor($width, $height);
         imagesetinterpolation($image_canvas, IMG_BICUBIC); // 关键优化点

         // 7. 处理透明背景
         if ($src_type === IMAGETYPE_PNG || $src_type === IMAGETYPE_GIF) {
             imagealphablending($image_canvas, false);
             imagesavealpha($image_canvas, true);
             $transparent = imagecolorallocatealpha($image_canvas, 255, 255, 255, 127);
             imagefill($image_canvas, 0, 0, $transparent);
         } else {
             $white = imagecolorallocate($image_canvas, 255, 255, 255);
             imagefill($image_canvas, 0, 0, $white);
         }

         // 8. 加载原图并缩放
         $imagecreatefromfunc = 'imagecreatefrom' . ($src_type === IMAGETYPE_JPEG ? 'jpeg' : image_type_to_extension($src_type, false));
         $image_resources = $imagecreatefromfunc($srcFile);
         imagecopyresampled($image_canvas, $image_resources, 0, 0, 0, 0, $width, $height, $src_width, $src_height);

         // 9. 锐化处理（关键优化点）
         if (function_exists('imageconvolution')) {
             $sharpen_matrix = [
                 [-1, -1, -1],
                 [-1, 24, -1],  // 调整这个值控制锐化强度（建议16-32）
                 [-1, -1, -1]
             ];
             $divisor = array_sum(array_map('array_sum', $sharpen_matrix));
             imageconvolution($image_canvas, $sharpen_matrix, $divisor, 0);
         }

         // 10. 输出图像
         if ($type == 2) { // 缩略模式
             if ($image_type === 'webp') {
                 imagewebp($image_canvas, $srcFile, 85); // WebP质量85
             } else {
                 $imagefunc = 'image' . $image_type;
                 if ($image_type === 'jpeg') {
                     imageinterlace($image_canvas, true); // 渐进式JPEG
                     $imagefunc($image_canvas, $srcFile, 88); // JPEG质量88
                 } else {
                     $imagefunc($image_canvas, $srcFile);
                 }
             }
         } else { // 压缩模式
             imagejpeg($image_canvas, $srcFile, $number);
         }

         // 11. 释放资源
         imagedestroy($image_canvas);
         imagedestroy($image_resources);

         return true;
     }*/
}
