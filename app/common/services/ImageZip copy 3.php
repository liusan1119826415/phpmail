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
            'thumb' => ['width' => 300, 'height' => 300, 'quality' => 90, 'webp' => true, 'min_compress_width' => 300],
            'medium_thumb' => ['width' => 512, 'height' => 512, 'quality' => 100, 'webp' => true, 'min_compress_width' => 512],
            'original' => ['width' => 3000, 'height' => 3000, 'quality' => 100, 'webp' => false, 'min_compress_width' => 800],
            'goodsmain' => ['width' => 748, 'height' => 748, 'quality' => 95, 'webp' => true, 'min_compress_width' => 800],
            'main' => ['width' => 748, 'height' => 748, 'quality' => 90, 'webp' => false, 'min_compress_width' => 800],
            'detail' => ['width' => 1200, 'height' => 1200, 'quality' => 95, 'webp' => false, 'min_compress_width' => 800],
        ];

        if (!isset($config[$mode])) {
            \Log::error("Invalid mode: {$mode}");
            return false;
        }

        if (!is_file($srcFile)) {
            \Log::error("Source file not found: {$srcFile}");
            return false;
        }

        // 检查是否支持Imagick
        if (extension_loaded('imagick')) {
            return self::processWithImagick($srcFile, $mode, $uniqueId, $config);
        } else {
            \Log::warning("Imagick extension not available, falling back to GD");
            return self::processWithGD($srcFile, $mode, $uniqueId, $config);
        }
    }

    /**
     * 使用Imagick处理图片（保持DPI）
     */
    private static function processWithImagick($srcFile, $mode, $uniqueId, $config)
    {
        $target_width = $config[$mode]['width'];
        $target_height = $config[$mode]['height'];
        $quality = $config[$mode]['quality'];
        $generate_webp = $config[$mode]['webp'];
        $min_compress_width = $config[$mode]['min_compress_width'];

        // 创建临时目录
        $tempDir = sys_get_temp_dir() . '/image_processing/';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        try {
            // 读取原图
            $imagick = new \Imagick($srcFile);

            // 获取原图信息
            $src_width = $imagick->getImageWidth();
            $src_height = $imagick->getImageHeight();

  

            // 获取原图DPI信息
            $resolution = $imagick->getImageResolution();
            $originalDpiX = $resolution['x'];
            $originalDpiY = $resolution['y'];

            // 如果原图没有DPI信息，设置为默认值300
            if ($originalDpiX == 0 || $originalDpiY == 0) {
                $originalDpiX = $originalDpiY = 300;
                $imagick->setImageResolution($originalDpiX, $originalDpiY);
            }

            // 检查是否需要压缩
            $should_compress = true;
            $final_quality = $quality;

            if ($src_width < $min_compress_width) {
                $should_compress = false;
                $final_quality = 100;
                \Log::info("Image width {$src_width}px is less than minimum compress width {$min_compress_width}px for mode {$mode}, using original size with quality 100");
            }

            // 计算最终尺寸
            if ($mode === 'detail') {
       
                if (!$should_compress) {
                    // 不压缩或宽度小于目标宽度，使用原图
                    $width = $src_width;
                    $height = $src_height;
                } else {
                    // 需要压缩且宽度大于等于目标宽度
                    if ($src_width >= $src_height) {
                        // 宽图或方图：以宽度为基准，保持宽高比
                        $width = $target_width;
                        $height = intval($src_height * $width / $src_width);

                        // 如果高度超过目标高度，则以高度为基准重新计算
                        if ($height > $target_height) {
                            $height = $target_height;
                            $width = intval($src_width * $height / $src_height);
                        }
                    } else {
                        // 高图：以高度为基准，保持宽高比
                        $height = $target_height;
                        $width = intval($src_width * $height / $src_height);

                        // 如果宽度超过目标宽度，则以宽度为基准重新计算
                        if ($width > $target_width) {
                            $width = $target_width;
                            $height = intval($src_height * $width / $src_width);
                        }
                    }
                }
            } else {
          
                if (!$should_compress) {
                    // 不压缩或宽度小于等于目标宽度，使用原图
                    $width = $src_width;
                    $height = $src_height;
                } else {
   
                    // 需要压缩且宽度大于目标宽度
                    if ($src_width >= $src_height) {
                        // 宽图或方图：以宽度为基准，保持宽高比
                        $width = $target_width;
                        $height = intval($src_height * $width / $src_width);

                        // 如果高度超过目标高度，则以高度为基准重新计算
                        if ($height > $target_height) {
                            $height = $target_height;
                            $width = intval($src_width * $height / $src_height);
                        }
                    } else {
         
                        // 高图：以高度为基准，保持宽高比
                        $height = $target_height;
                        $width = intval($src_width * $height / $src_height);

                        // 如果宽度超过目标宽度，则以宽度为基准重新计算
                        if ($width > $target_width) {
                            $width = $target_width;
                            $height = intval($src_height * $width / $src_width);
                        }
                    }
                }
            }

            // 如果不需压缩且尺寸相同，直接转换
            if (!$should_compress && $width == $src_width && $height == $src_height) {
                $clone = clone $imagick;
                $clone->setImageFormat('jpeg');
                $clone->setImageCompression(\Imagick::COMPRESSION_JPEG);
                $clone->setImageCompressionQuality($final_quality);
                $clone->setImageBackgroundColor('white');
                $clone->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);

                $jpegPath = $tempDir . basename($srcFile, '.' . pathinfo($srcFile, PATHINFO_EXTENSION)) . "_{$mode}_{$uniqueId}.jpg";
                $clone->writeImage($jpegPath);
                $clone->destroy();

                // 生成WebP
                $webpPath = null;
                if ($generate_webp) {
                    $webpImage = clone $imagick;
                    $webpImage->setImageFormat('webp');
                    $webpImage->setImageCompressionQuality($final_quality);
                    $webpPath = $tempDir . basename($srcFile, '.' . pathinfo($srcFile, PATHINFO_EXTENSION)) . "_{$mode}_{$uniqueId}.webp";
                    $webpImage->writeImage($webpPath);
                    $webpImage->destroy();
                }

                $imagick->destroy();
                return $webpPath ?: $jpegPath;
            }

            // 如果需要调整大小
            if ($width != $src_width || $height != $src_height) {
                // 使用高质量的重采样算法
                $imagick->resizeImage($width, $height, \Imagick::FILTER_LANCZOS, 1);
            }

            // 确保保持DPI信息
            $imagick->setImageResolution($originalDpiX, $originalDpiY);

            // 处理透明度（转换为JPEG时需要）
            if ($imagick->getImageAlphaChannel()) {
                $imagick->setImageBackgroundColor('white');
                $imagick->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
                $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
            }

            // 设置JPEG输出选项
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompression(\Imagick::COMPRESSION_JPEG);
            $imagick->setImageCompressionQuality($final_quality);

            // 保存JPEG
            $jpegPath = $tempDir . basename($srcFile, '.' . pathinfo($srcFile, PATHINFO_EXTENSION)) . "_{$mode}_{$uniqueId}.jpg";
            $imagick->writeImage($jpegPath);

            // 生成WebP
            $webpPath = null;
            if ($generate_webp) {
                // 克隆一份用于WebP
                $webpImage = clone $imagick;
                $webpImage->setImageFormat('webp');
                $webpImage->setImageCompressionQuality($final_quality);
                $webpPath = $tempDir . basename($srcFile, '.' . pathinfo($srcFile, PATHINFO_EXTENSION)) . "_{$mode}_{$uniqueId}.webp";
                $webpImage->writeImage($webpPath);
                $webpImage->destroy();
            }

            $imagick->destroy();

            // 验证DPI是否保持
            if (file_exists($jpegPath)) {
                $checkImagick = new \Imagick($jpegPath);
                $checkResolution = $checkImagick->getImageResolution();
                \Log::info("Processed image DPI: X={$checkResolution['x']}, Y={$checkResolution['y']}");
                $checkImagick->destroy();
            }

            return $webpPath ?: $jpegPath;
        } catch (\Exception $e) {
            \Log::error("Imagick processing failed: " . $e->getMessage());

            // 如果Imagick失败，回退到GD
            \Log::warning("Falling back to GD processing");
            return self::processWithGD($srcFile, $mode, $uniqueId, $config);
        }
    }

    /**
     * 使用GD处理图片（回退方案）
     */
    private static function processWithGD($srcFile, $mode, $uniqueId, $config)
    {
        // 原有的GD处理代码，这里只是将原有代码移动到这里
        $target_width = $config[$mode]['width'];
        $quality = $config[$mode]['quality'];
        $generate_webp = $config[$mode]['webp'];
        $min_compress_width = $config[$mode]['min_compress_width'];

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

        // 创建临时目录
        $tempDir = sys_get_temp_dir() . '/image_processing/';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        // 检查是否需要压缩
        $should_compress = true;
        $final_quality = $quality;

        if ($src_width < $min_compress_width) {
            $should_compress = false;
            $final_quality = 100;
            \Log::info("Image width {$src_width}px is less than minimum compress width {$min_compress_width}px for mode {$mode}, using original size with quality 100");
        }

        // 计算最终尺寸
        if ($mode === 'detail') {
            if (!$should_compress || $src_width < $target_width) {
                $width = $src_width;
                $height = $src_height;
            } else {
                $width = $target_width;
                $height = intval($src_height * $width / $src_width);
            }
        } else {
            if (!$should_compress || $src_width <= $target_width) {
                $width = $src_width;
                $height = $src_height;
            } else {
                $width = $target_width;
                $height = intval($src_height * $width / $src_width);
            }
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
                imagecopy($image_canvas, $image_resources, 0, 0, 0, 0, $width, $height);
            } else {
                imagecopyresampled($image_canvas, $image_resources, 0, 0, 0, 0, $width, $height, $src_width, $src_height);
            }

            // 处理透明背景
            $flatten_canvas = imagecreatetruecolor($width, $height);
            $white = imagecolorallocate($flatten_canvas, 255, 255, 255);
            imagefill($flatten_canvas, 0, 0, $white);
            imagecopy($flatten_canvas, $image_canvas, 0, 0, 0, 0, $width, $height);

            // 输出JPG
            $jpegPath = $tempDir . basename($srcFile, '.' . pathinfo($srcFile, PATHINFO_EXTENSION)) . "_{$mode}_{$uniqueId}.jpg";
            imagejpeg($flatten_canvas, $jpegPath, $final_quality);
            imagedestroy($flatten_canvas);

            // 生成WebP
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

            // GD无法保持DPI，这里可以尝试用其他方法添加DPI信息
            if (function_exists('exif_read_data')) {
                self::tryToPreserveDpi($srcFile, $jpegPath);
            }

            return $webpPath ?: $jpegPath;
        } catch (\Exception $e) {
            \Log::error("GD processing failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 尝试保持DPI信息（GD回退方案）
     */
    private static function tryToPreserveDpi($srcFile, $destFile)
    {
        try {
            // 尝试从原图读取DPI
            if (function_exists('exif_read_data')) {
                $exif = @exif_read_data($srcFile);
                if ($exif && isset($exif['XResolution']) && isset($exif['YResolution'])) {
                    $xRes = intval($exif['XResolution']);
                    $yRes = intval($exif['YResolution']);

                    // 如果有exiftool，可以使用它来设置DPI
                    if (self::hasExifTool()) {
                        $cmd = "exiftool -overwrite_original -XResolution={$xRes} -YResolution={$yRes} -ResolutionUnit=inches \"{$destFile}\" 2>&1";
                        @shell_exec($cmd);
                    }
                }
            }
        } catch (\Exception $e) {
            // 忽略DPI设置错误
        }
    }

    /**
     * 检查是否安装exiftool
     */
    private static function hasExifTool()
    {
        static $hasExifTool = null;
        if ($hasExifTool === null) {
            $result = @shell_exec("which exiftool 2>/dev/null || where exiftool 2>/dev/null");
            $hasExifTool = !empty($result);
        }
        return $hasExifTool;
    }



    /**
     * 不压缩处理 - 直接转换格式和质量（使用Imagick）
     */
    private static function processWithoutCompression($srcFile, $width, $height, $src_type, $mode, $uniqueId, $quality, $generate_webp, $tempDir)
    {
        // 优先使用Imagick
        if (extension_loaded('imagick')) {
            return self::processWithoutCompressionImagick($srcFile, $width, $height, $mode, $uniqueId, $quality, $generate_webp, $tempDir);
        } else {
            \Log::warning("Imagick extension not available, falling back to GD for uncompressed processing");
            return self::processWithoutCompressionGD($srcFile, $width, $height, $src_type, $mode, $uniqueId, $quality, $generate_webp, $tempDir);
        }
    }

    /**
     * 使用Imagick处理不压缩的情况
     */
    private static function processWithoutCompressionImagick($srcFile, $width, $height, $mode, $uniqueId, $quality, $generate_webp, $tempDir)
    {
        try {
            $imagick = new \Imagick($srcFile);

            // 获取原图DPI信息
            $resolution = $imagick->getImageResolution();
            $originalDpiX = $resolution['x'];
            $originalDpiY = $resolution['y'];

            // 如果原图没有DPI信息，设置为默认值300
            if ($originalDpiX == 0 || $originalDpiY == 0) {
                $originalDpiX = $originalDpiY = 300;
            }

            // 设置DPI信息
            $imagick->setImageResolution($originalDpiX, $originalDpiY);

            // 确保图像尺寸与指定的一致（应该一致，因为是"不压缩"处理）
            $currentWidth = $imagick->getImageWidth();
            $currentHeight = $imagick->getImageHeight();

            if ($currentWidth != $width || $currentHeight != $height) {
                \Log::warning("Image dimensions mismatch in uncompressed processing: expected {$width}x{$height}, got {$currentWidth}x{$currentHeight}");
            }

            // 处理透明度（转换为JPEG时需要）
            if ($imagick->getImageAlphaChannel()) {
                $imagick->setImageBackgroundColor('white');
                $imagick->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
                $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
            }

            // 设置JPEG输出选项
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompression(\Imagick::COMPRESSION_JPEG);
            $imagick->setImageCompressionQuality($quality);

            // 保存JPEG
            $jpegPath = $tempDir . basename($srcFile, '.' . pathinfo($srcFile, PATHINFO_EXTENSION)) . "_{$mode}_{$uniqueId}.jpg";
            $imagick->writeImage($jpegPath);

            // 生成WebP
            $webpPath = null;
            if ($generate_webp) {
                // 重新读取原图以生成WebP（保持透明度和原图质量）
                $webpImage = new \Imagick($srcFile);
                $webpImage->setImageResolution($originalDpiX, $originalDpiY);
                $webpImage->setImageFormat('webp');
                $webpImage->setImageCompressionQuality($quality);

                $webpPath = $tempDir . basename($srcFile, '.' . pathinfo($srcFile, PATHINFO_EXTENSION)) . "_{$mode}_{$uniqueId}.webp";
                $webpImage->writeImage($webpPath);
                $webpImage->destroy();
            }

            $imagick->destroy();

            // 记录DPI信息
            \Log::info("Uncompressed processing - Original DPI maintained: {$originalDpiX}x{$originalDpiY}");

            return $webpPath ?: $jpegPath;
        } catch (\Exception $e) {
            \Log::error("Imagick uncompressed processing failed: " . $e->getMessage());
            // 回退到GD处理
            return self::processWithoutCompressionGD(
                $srcFile,
                $width,
                $height,
                self::getImageTypeFromExtension($srcFile),
                $mode,
                $uniqueId,
                $quality,
                $generate_webp,
                $tempDir
            );
        }
    }

    /**
     * 使用GD处理不压缩的情况（回退方案）
     */
    private static function processWithoutCompressionGD($srcFile, $width, $height, $src_type, $mode, $uniqueId, $quality, $generate_webp, $tempDir)
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

            // GD无法保持DPI，尝试用exiftool添加DPI信息
            self::tryToPreserveDpi($srcFile, $jpegPath);

            return $webpPath ?: $jpegPath;
        } catch (\Exception $e) {
            \Log::error("GD uncompressed processing failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 根据文件扩展名获取图像类型
     */
    private static function getImageTypeFromExtension($srcFile)
    {
        $extension = strtolower(pathinfo($srcFile, PATHINFO_EXTENSION));
        $type_map = [
            'gif' => 1,
            'jpg' => 2,
            'jpeg' => 2,
            'png' => 3,
            'wbmp' => 15,
            'webp' => 18
        ];

        return isset($type_map[$extension]) ? $type_map[$extension] : 2; // 默认JPEG
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
