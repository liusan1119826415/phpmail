<?php

namespace business\admin\controllers;

use app\common\facades\Setting;
use app\common\services\MiniFileLimitService;
use app\common\services\upload\UploadService;
use business\common\controllers\components\BaseController;

class UploadController extends BaseController
{
    public function uploadPic()
    {
        $attach = request()->attach;
        $ingress = request()->ingress;
        $file = request()->file('file');
        $local_upload = request()->local_upload == 1 ? true : false;
        $upload_type = request()->upload_type;
        if (!$file) {
            return $this->errorJson('请传入正确参数.');
        }
        if (!$file->isValid()) {
            return $this->errorJson('上传失败.');
        }
        if ($ingress && $upload_type == 'image' && $this->isMiniCheckImage()) {
            if ($file->getSize() > 1024 * 1024) {
                return $this->errorJson('小程序图片安全验证图片不能大于1M');
            }
            $check_result = (new MiniFileLimitService())->checkImg($file);
            if ($check_result['errcode'] == 87014) {
                return $this->errorJson('内容含有违法违规信息');
            }
        }
        $file_name = '';
        if (request()->need_name) {
            $file_name = $file->getClientOriginalName();
        }
        try {
            if ($local_upload) {
                $url_arr = (new UploadService())->upload($file, $upload_type, '', $file_name, false);
            } else {
                $url_arr = (new UploadService())->upload($file, $upload_type, '', $file_name);
            }
        }catch (\Exception $e){
            return $this->errorJson($e->getMessage());
        }
        return $this->successJson('上传成功', [
            'img' => $url_arr['relative_path'],
            'img_url' => $url_arr['absolute_path'],
            'attach' => $attach,
        ]);
    }

    public function downloadFile()
    {
        @ini_set('memory_limit', -1);

        $url = request()->url;
        $temp = file_get_contents($url);
        if ($temp === false){
            http_response_code(404);
            die();
        }
        $name = basename($url);
        ob_clean();
        header('Content-Type:application/octet-stream');
        header('Content-Disposition:attachment; filename=' . $name);
        echo $temp;
        die();
    }

    protected function isMiniCheckImage()
    {
        if (!app('plugins')->isEnabled('min-app')) {
            return false;
        }
        $set = Setting::get('plugin.min_app');
        if ($set['switch'] != 1) {
            return false;
        }
        if ($set['image_check'] != 1) {
            return false;
        }
        return true;
    }
}
