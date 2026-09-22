<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2021-04-26
 * Time: 15:59
 */

namespace app\common\services\upload;


use app\common\exceptions\ShopException;
use app\common\services\ImageZip;
use app\platform\modules\system\models\SystemSetting;

class UploadService
{
    private $setting;
    private $originalName;
    private $realPath;
    private $ext;
    private $mime_type;
    private $fileSize;
    private $diyFileName;
    private $is_remote;
    private $dir;
    private $fileNewName;
    private $file_type;
    private $relative_path;

    private $thumb_type;

    private $is_high;

    private $high_path;

    private $high_relative_path = '';

    private $webp_path = '';

    private $webp_relative_path ='';

    private $webp_thumb_path ='';

    private $webp_thumb_relative_path ='';

    private $harm_type = array('asp', 'php', 'jsp', 'js', 'css', 'php3', 'php4', 'php5', 'ashx', 'aspx', 'exe', 'cgi');

    private $default_audio_types = array(
        'avi', 'asf', 'wmv', 'avs', 'flv', 'mkv', 'mov', '3gp', 'mp4', 'mpg', 'mpeg', 'dat', 'ogm', 'vob', 'rm', 'rmvb', 'ts', 'tp', 'ifo', 'nsv',
    );
    private $default_video_types = array(
        'mp3', 'aac', 'wav', 'wma', 'cda', 'flac', 'm4a', 'mid', 'mka', 'mp2', 'mpa', 'mpc', 'ape', 'ofr', 'ogg', 'ra', 'wv', 'tta', 'ac3', 'dts',
    );
    private $default_image_types = array(
        'jpg', 'bmp', 'eps', 'gif', 'mif', 'miff', 'png', 'tif', 'tiff', 'svg', 'wmf', 'jpe', 'jpeg', 'dib', 'ico', 'tga', 'cut', 'pic'
    );
    private $default_file_types = array(
        'pdf', 'xlsx', 'xls', 'doc', 'docx', 'txt', 'ppt', 'pptx', 'xml', 'wps', 'rtf', 'md', 'rar', 'zip', 'et', 'json','7z','dxf','dwg','max','3ds','glb','obj','stl','dae','drc'
    );
    private $default_file_mime_type = [
        'audio/aac', 'video/x-msvideo', 'image/bmp', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/gif',
        'image/vnd.microsoft.icon', 'image/jpeg', 'audio/midi', 'audio/x-midi', 'audio/mpeg', 'video/mpeg', 'image/png', 'application/pdf', 'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/x-rar-compressed', 'application/rtf', 'image/svg+xml', 'image/tiff',
        'text/plain', 'audio/wav', 'image/webp', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/xml', 'text/xml',
        'video/3gpp', 'audio/3gpp', 'video/x-ms-asf', 'video/x-ms-wmv', 'video/x-flv', 'video/quicktime', 'video/mp4', 'audio/x-wav', 'audio/x-m4a', 'audio/mid', 'audio/ogg',
        'audio/x-realaudio', 'application/postscript', 'application/x-msmetafile', 'image/x-icon', 'application/vnd.ms-works', 'application/rar', 'application/zip', 'application/x-rar',
        'application/octet-stream', 'application/x-font-gdos',"application/CDFV2","image/vnd.dwg","application/vnd.ms-office"
    ];

    public function __construct($is_high=0,$thumb_type='detail')
    {
         $this->is_high = request()->input('is_high',$is_high);

         $this->thumb_type = request()->input('thumb_type',$thumb_type);
    }

    public function uploadTwo($file, $file_type = 'image', $dir = '', $diy_file_name = '', $is_remote = true)
    {
        if (!$dir) {
            $this->dir = $this->getDirByType($file_type);
        } else {
            $this->dir = $dir;
        }
        $this->file_type = $file_type;
        $this->diyFileName = $diy_file_name;
        $this->is_remote = $is_remote;
        $this->setting = $this->getSetting();
        $this->initFile($file);
        $this->checkFile();
        $this->handleFileHight();
        $this->localUpload();
        $this->rotatePic();
        if ($this->setting['remote']['type'] != 0 && $is_remote) {
            $this->remoteUpload();
        }
        $url = $this->getUrl();
        $this->examine($url);
        return [
            'relative_path' => $this->getDiskUrl(),
            'absolute_path' => $url,
            'file_name' => $this->getFileName(),
        ];

    }


    public function upload($file, $file_type = 'image', $dir = '', $diy_file_name = '', $is_remote = true)
    {

        if (!$dir) {
            $this->dir = $this->getDirByType($file_type);
        } else {
            $this->dir = $dir;
        }
        $this->file_type = $file_type;
        $this->diyFileName = $diy_file_name;
        $this->is_remote = $is_remote;
        $this->setting = $this->getSetting();
        $this->initFile($file);
        $this->checkFile();
        $this->handleFile();
        $this->localUpload();
        $this->rotatePic();

        if ($this->setting['remote']['type'] != 0 && $is_remote) {
            $this->remoteUpload();
        }

        $url = $this->getUrl();
        $this->examine($url);
        return [
            'relative_path' => $this->getDiskUrl(),
            'absolute_path' => $url,
            'file_name' => $this->getFileName(),
            'high_relative_path' => $this->high_relative_path,
            'high_absolute_path' => $this->high_path,
            'webp_path' => $this->webp_path,
            'webp_absolute_path' => $this->webp_thumb_absolute_path ?? '',
            'webp_thumb_path' => $this->webp_thumb_path,
            'webp_thumb_absolute_path' => $this->webp_thumb_absolute_path ?? '',
        ];
    }

    /**
     * 导入工具专用上传（跳过扩展名校验）
     */
    public function uploadForImport($file, $file_type = 'image', $dir = '', $diy_file_name = '', $is_remote = true)
    {
        if (!$dir) {
            $this->dir = $this->getDirByType($file_type);
        } else {
            $this->dir = $dir;
        }
        $this->file_type = $file_type;
        $this->diyFileName = $diy_file_name;
        $this->is_remote = $is_remote;
        $this->setting = $this->getSetting();
        $this->initFile($file);
        // 跳过 checkFile() 扩展名校验
        $this->handleFile();
        $this->localUpload();
        $this->rotatePic();

        if ($this->setting['remote']['type'] != 0 && $is_remote) {
            $this->remoteUpload();
        }

        $url = $this->getUrl();
        return [
            'relative_path' => $this->getDiskUrl(),
            'absolute_path' => $url,
            'file_name' => $this->getFileName(),
            'high_relative_path' => $this->high_relative_path,
            'high_absolute_path' => $this->high_path,
            'webp_path' => $this->webp_path,
            'webp_absolute_path' => $this->webp_thumb_absolute_path ?? '',
            'webp_thumb_path' => $this->webp_thumb_path,
            'webp_thumb_absolute_path' => $this->webp_thumb_absolute_path ?? '',
        ];
    }




    private function getDirByType($upload_type)
    {
        switch ($upload_type) {
            case 'video' :
                $dir = 'videos';
                break;
            case 'audio' :
                $dir = 'audios';
                break;
            case 'file' :
                $dir = 'files';
                break;
            default :
                $dir = 'image';
                break;
        }
        return $dir;
    }
    private function getFileName()
    {
        if ($this->diyFileName) {
            return $this->diyFileName;
        }
        if (isset($this->fileNewName)) {
            return $this->fileNewName;
        } else {
            $this->fileNewName = md5($this->originalName.str_random(6)).'.'.$this->ext;
        }
        return $this->fileNewName;
    }
    private function getDiskUrl()
    {
        return $this->relative_path;
    }
   /* private function localUpload()
    {
        $uniacid = intval(\YunShop::app()->uniacid);
        $path = $this->file_type.'s/'.$uniacid.'/'.date('Y/m/');
        $dir = $this->basePath().'/'.$path;
        $this->mkDir($dir);
        $file_name = $this->getFileName();
        $save_path = $dir.$file_name;
        $relative_path = $path.$file_name;
        $this->relative_path = $relative_path;

        if (!$this->fileMove($this->realPath, $save_path)) {
            return false;
        }

        // 处理 WebP 文件
        if(in_array($this->thumb_type, ['goodsmain', 'thumb','medium_thumb'])) {
            // 保存主 WebP 文件

            if($this->thumb_type == "goodsmain" && !empty($this->webp_path) && file_exists($this->webp_path)) {
                $webp_file_name = pathinfo($file_name, PATHINFO_FILENAME) . '.webp';
                $webp_save_path = $dir . $webp_file_name;
                $this->webp_relative_path = $path . $webp_file_name;

                if (!$this->fileMove($this->webp_path, $webp_save_path)) {
                    // 如果移动失败，记录错误但不中断整个流程
                    \Log::error('Failed to move WebP file: ' . $this->webp_path);
                }
            }

            // 保存缩略图 WebP 文件
            if (!empty($this->webp_thumb_path) && file_exists($this->webp_thumb_path)) {
                $webp_thumb_name = pathinfo($file_name, PATHINFO_FILENAME) . 'thumb.webp';
                $webp_thumb_save_path = $dir . $webp_thumb_name;
                $this->webp_thumb_relative_path = $path . $webp_thumb_name;

                if (!$this->fileMove($this->webp_thumb_path, $webp_thumb_save_path)) {
                    \Log::error('Failed to move WebP thumb file: ' . $this->webp_thumb_path);
                }
            }
        }

        if($this->is_high == 1) {
            $file_name = $this->getFileName();
            $save_path = $dir.$file_name;
            $relative_path = $path.$file_name;
            $this->high_relative_path = $relative_path;

            if (!$this->fileMove($this->high_path, $save_path)) {
                return false;
            }
        }

        return true;
    }*/

    private function localUpload()
    {
        
        $uniacid = intval(\YunShop::app()->uniacid);
        $path = $this->file_type.'s/'.$uniacid.'/'.date('Y/m/');
        $dir = $this->basePath().'/'.$path;
        $this->mkDir($dir);

        // 处理普通图片
        $file_name = $this->getFileName();
        $save_path = $dir.$file_name;
        $relative_path = $path.$file_name;
        $this->relative_path = $relative_path;

        if (!$this->fileMove($this->realPath, $save_path)) {
       
            return false;
        }else{
            $this->clearFile($this->realPath);
        }

        // 处理 WebP 文件
        if(in_array($this->thumb_type, ['goodsmain', 'thumb','medium_thumb'])) {
            // 保存主 WebP 文件
            if($this->thumb_type == "goodsmain" && !empty($this->webp_path) && file_exists($this->webp_path)) {
                $webp_file_name = pathinfo($file_name, PATHINFO_FILENAME) . '.webp';
                $webp_save_path = $dir . $webp_file_name;
                $this->webp_relative_path = $path . $webp_file_name;

                if (!$this->fileMove($this->webp_path, $webp_save_path)) {
                    \Log::error('Failed to move WebP file: ' . $this->webp_path);
                } else {
                    // 移动成功后删除临时文件
                    $this->clearFile($this->webp_path);
                }
            }

            // 保存缩略图 WebP 文件
            if (!empty($this->webp_thumb_path) && file_exists($this->webp_thumb_path)) {
                $webp_thumb_name = pathinfo($file_name, PATHINFO_FILENAME) . 'thumb.webp';
                $webp_thumb_save_path = $dir . $webp_thumb_name;
                $this->webp_thumb_relative_path = $path . $webp_thumb_name;

                if (!$this->fileMove($this->webp_thumb_path, $webp_thumb_save_path)) {
                    \Log::error('Failed to move WebP thumb file: ' . $this->webp_thumb_path);
                }else {
                    $this->clearFile($this->webp_thumb_path);
                }
            }
        }

        // 处理高清图片（单独的文件名和路径）
        if($this->is_high == 1) {
            $high_file_name = 'high_' . $this->getFileName(); // 添加前缀区分
            $high_save_path = $dir . $high_file_name;
            $high_relative_path = $path . $high_file_name;
            $this->high_relative_path = $high_relative_path;

            if (!$this->fileMove($this->high_path, $high_save_path)) {
                \Log::error('Failed to move high quality image: ' . $this->high_path);
                // 不返回false，允许普通图片上传成功
            }else {
                
                $this->clearFile($this->high_path);
            }
        }
        
        return true;
    }


    private function clearFile($save_path)
    {
        
           // 判断文件路径是否包含指定关键词，如果包含则删除文件
        $keywords = ['thumb', 'medium_thumb', 'goodsmain', 'main', 'detail', 'original'];
        foreach ($keywords as $keyword) {
            if (strpos($save_path, $keyword) !== false) {
                if (file_exists($save_path)) {
                    unlink($save_path);
                }
                break; // 找到一个匹配的关键词就删除并跳出循环
            }
        }
    }

      private function handleFile()
    {

        if ($this->file_type != 'image') {
            return;
        }

        /*if ($this->setting['upload']['thumb'] == 1 && $this->setting['upload']['width'] && $this->ext != 'gif') {
            ImageZip::makeThumb($this->realPath, $this->setting['upload']['width'], 2);
        }
        if ($this->setting['upload']['percent'] && $this->setting['upload']['percent'] != 100 && $this->ext != 'gif') {
            ImageZip::makeThumb($this->realPath, $this->setting['upload']['percent'], 1);
        }*/

        if($this->is_high == 1){

            $this->high_path = ImageZip::makeThumbWebp($this->realPath, 'original');


        }

        if ($this->ext !== 'gif') {
            if($this->thumb_type == "goodsmain"){
                $webp_path = ImageZip::makeThumbWebp($this->realPath, $this->thumb_type);
                if ($webp_path && file_exists($webp_path)) {
                    $this->webp_path = $webp_path;

                }

                $webp_thumb_path = ImageZip::makeThumbWebp($this->realPath, "thumb");

                if ($webp_thumb_path && file_exists($webp_thumb_path)) {
                    $this->webp_thumb_path = $webp_thumb_path;
                }

            }else{
                $realPath = ImageZip::makeThumbWebp($this->realPath, $this->thumb_type);
                if(in_array($this->thumb_type,["thumb","medium_thumb"])){
                    $this->webp_thumb_path = $realPath;
                }else{
                    $this->realPath = $realPath;
                }


            }







           /* if($this->thumb_type == 'main'){
                $webp_thumb_path = ImageZip::makeThumbWebp($this->realPath, "thumb");
                if ($webp_thumb_path && file_exists($webp_thumb_path)) {
                    $this->webp_thumb_path = $webp_thumb_path;
                }
            }*/





        }

    }

    private function remoteUpload()
    {
        if (config('app.framework') == 'platform') {
            if ($this->setting['remote']['type'] != 0) {



                if($this->thumb_type == "goodsmain"){
                    file_remote_upload($this->webp_relative_path,true, $this->setting['remote']);
                    file_remote_upload($this->webp_thumb_relative_path,true, $this->setting['remote']);
                }elseif(in_array($this->thumb_type,["thumb","medium_thumb"])){
                    file_remote_upload($this->webp_thumb_relative_path,true, $this->setting['remote']);
                }elseif(in_array($this->thumb_type,['detail'])){
                    file_remote_upload($this->getDiskUrl(),true, $this->setting['remote']);
                }

                if($this->is_high == 1){
                    file_remote_upload($this->high_relative_path,true, $this->setting['remote']);
                   
                }

                



            }
        } else {
            if ($this->setting['remote']['type'] != 0) {
                file_remote_upload_wq($this->getDiskUrl(), true, $this->setting['remote']);
            }
        }
        @unlink($this->high_path);
        @unlink($this->webp_path);
        @unlink($this->webp_thumb_path);
    }

    private function handleFileHight()
    {
        if ($this->file_type != 'image') {
            return;
        }
        if ($this->ext != 'gif') {
            ImageZip::makeThumb($this->realPath, 2500, 2);
        }
        if ($this->ext != 'gif') {
            ImageZip::makeThumb($this->realPath, 100, 1);
        }
    }

  
    private function getUrl()
    {
        if ($this->is_remote) {
            return yz_tomedia($this->getDiskUrl());
        } else {
            return change_to_local_url($this->getDiskUrl());
        }
    }
    public static function getSetting()
    {
        if (config('app.framework') == 'platform') {
            $global_setting = SystemSetting::settingLoad('global', 'system_global');
            $remote = SystemSetting::settingLoad('remote', 'system_remote');
            $upload['image_ext'] = $global_setting['image_extentions'];//图片文件拓展名
            $upload['image_limit'] = $global_setting['image_limit'];//图片文件限制大小
            $upload['percent'] = $global_setting['zip_percentage'];//图片压缩比例
            $upload['thumb'] = $global_setting['thumb'];//是否开启缩略
            $upload['width'] = $global_setting['thumb_width'];//缩略图最大宽度
            $upload['audio_ext'] = $global_setting['audio_extentions'];//音频文件拓展名
            $upload['audio_limit'] = $global_setting['audio_limit'];//音频文件限制大小
        } else {
            //全局配置
            global $_W;
            $global_upload = $_W['setting']['upload'];
            //公众号独立配置信息 优先使用公众号独立配置
            $uni_setting = app('WqUniSetting')->get()->toArray();
            if (!empty($uni_setting['remote']) && iunserializer($uni_setting['remote'])['type'] != 0) {
                $remote = iunserializer($uni_setting['remote']);
            } else {
                $remote = $_W['setting']['remote'];
            }
            $upload['image_ext'] = $global_upload['image']['extentions'];//图片文件拓展名
            $upload['image_limit'] = $global_upload['image']['limit'];//文件限制大小
            $upload['percent'] = $global_upload['image']['zip_percentage'];//压缩比例
            $upload['thumb'] = $global_upload['image']['thumb'];//是否开启缩略
            $upload['width'] = $global_upload['image']['width'];//缩略图最大宽度
            $upload['audio_ext'] = $global_upload['audio']['extentions'];//音频文件拓展名
            $upload['audio_limit'] = $global_upload['audio']['limit'];//音频文件限制大小
        }
        return array('upload' => $upload, 'remote' => $remote);
    }
    private function initFile($file)
    {

        $this->originalName = $file->getClientOriginalName(); // 文件原名

        $this->realPath = $file->getRealPath(); //临时文件的绝对路径
        $this->ext = strtolower($file->getClientOriginalExtension()); //文件后缀
        //$this->handelMimeType($file);
        if ($this->file_type == 'image') {
            $this->ext = strtolower($file->getClientOriginalExtension()) ?: 'png';
        }
        if (!$this->ext && !empty($file->getMimeType())) {//兼容上传文件为前端转过格式的文件，获取不了后缀名情况
            $type = explode('/',$file->getMimeType());
            !empty($type[1]) || $type[1] = '';
            switch ($type[1]) {
                case 'x-wav':
                    $this->ext = 'wav';break;
            }
        }
        $this->fileSize = $file->getSize(); //文件大小
        $this->mime_type = $file->getMimeType();

    }
    private function handelMimeType($file)
    {
        $mime_type = $file->getClientMimeType(); //获取文件类型
        if (strexists($mime_type, 'image')) {
            $this->file_type = 'image';
        }
        if (strexists($mime_type, 'video')) {
            $this->file_type = 'video';
        }
        if (strexists($mime_type, 'audio')) {
            $this->file_type = 'audio';
        }
    }
    private function checkFile()
    {

        if (!in_array($this->mime_type, $this->default_file_mime_type)) {
            throw new ShopException('无法识别的文件mime类型：'.$this->mime_type);
        }
        if (in_array($this->ext, $this->harm_type)) {
            throw new ShopException('请上传正确的文件格式');
        }

        if (!in_array($this->ext, array_merge($this->default_image_types, $this->default_video_types, $this->default_audio_types, $this->default_file_types))) {
            throw new ShopException('非规定类型的文件默认格式.');
        }

        if ($this->file_type == 'image' && !in_array($this->ext, $this->setting['upload']['image_ext'])) {

            throw new ShopException('非规定类型的图片文件格式.');
        }
        if (($this->file_type == 'video' || $this->file_type == 'audio') && !in_array($this->ext, $this->setting['upload']['audio_ext'])) {
            throw new ShopException('非规定类型的音频文件格式.');
        }
        $default_img_size = $this->setting['upload']['image_limit'] ? $this->setting['upload']['image_limit'] * 1024 : 1024 * 1024 * 5;
        if ($this->file_type == 'image' && $this->fileSize > $default_img_size) {
            throw new ShopException('图片文件大小超出规定值.');
        }
        $default_audio_size = $this->setting['upload']['audio_limit'] ? $this->setting['upload']['audio_limit'] * 1024 : 1024 * 1024 * 25;
        if (($this->file_type == 'video' || $this->file_type == 'audio'|| $this->file_type == 'file') && $this->fileSize > $default_audio_size) {
            throw new ShopException('音频或文件大小超出规定值.');
        }
        return true;
    }
    private function examine($url)
    {
        if (app('plugins')->isEnabled('upload-verification')) {
            if (in_array($this->ext, ['png','jpg','jpeg','bmp','gif','webp','tiff'])) {
                $uploadResult = do_upload_verificaton($url, 'img');
                if (0 === $uploadResult[0]['status']) {
                    throw new ShopException('内容审核插件报错，请检查配置:'.$uploadResult[0]['msg']);
                }
            }
            if ($this->file_type == 'audio') {
                $uploadResult = do_upload_verificaton($url, 'audio');
                if (0 === $uploadResult[0]['status']) {
                    throw new ShopException('内容审核插件报错，请检查配置:'.$uploadResult[0]['msg']);
                }
            }
            if ($this->file_type == 'video') {
                $uploadResult = do_upload_verificaton($url, 'video');
                if (0 === $uploadResult[0]['status']) {
                    throw new ShopException('内容审核插件报错，请检查配置:'.$uploadResult[0]['msg']);
                }
            }
        }
    }
    private function rotatePic()
    {
        $url = change_to_local_url($this->getDiskUrl());
        if (!in_array($this->ext, ['png','jpg','jpeg','bmp','gif','webp','tiff'])) {
            return false;
        }
        $img_size = getimagesize($url);
        list($src_width, $src_height) = $img_size;
        $memory_limit = trim(ini_get('memory_limit'), 'M');
        $img_memory = $src_width * $src_height * 3 * 1.7;
        if ($img_memory > $memory_limit * 1024 * 1024) { //imagecreatetruecolor方法生成图片资源时会占用大量的服务器内存，所以在上传大图、长图时不能使用
            return false;
        }
        if (function_exists('exif_read_data')) {
            $exif = exif_read_data($url);
            if (!$exif) {
                return false;
            }
            $image = imagecreatefromstring(file_get_contents($url));
            if (!empty($exif['Orientation'])) {
                switch ($exif['Orientation']) {
                    case 8:
                        $image = imagerotate($image, 90, 0);
                        break;
                    case 3:
                        $image = imagerotate($image, 180, 0);
                        break;
                    case 6:
                        $image = imagerotate($image, -90, 0);
                        break;
                }
                if ($exif['Orientation'] != 1) {
                    if ($exif['MimeType'] == 'image/gif') {
                        imagegif($image, $url);
                    } else if($exif['MimeType'] == 'image/png') {
                        imagepng($image, $url);
                    } else {
                        imagejpeg($image, $url);
                    }
                }
            }
        }
        return true;
    }
    private function basePath()
    {
        if (config('app.framework') == 'platform') {
            $path = base_path('static/upload');
        } else  {
            $path = dirname(dirname(base_path())).'/attachment';
        }
        return $path;
    }
    private function fileMove($filename, $dest)
    {
        $this->mkDir(dirname($dest));
        if (is_uploaded_file($filename)) {
          
            move_uploaded_file($filename, $dest);
        } else {
       
           // rename($filename, $dest);
           copy($filename, $dest);
        }
        @chmod($filename, 0777);
        return is_file($dest);
    }
    private function mkDir($dir)
    {
        return is_dir($dir) or self::mkDir(dirname($dir)) and mkdir($dir, 0777);
    }
}
