<?php

namespace app\framework\EasyWechat\Work;

use app\common\exceptions\AppException;
use business\common\models\Material;

class Media extends \EasyWeChat\Work\Media\Client
{

    /**
     * 处理带空格的链接
     * @param string $url
     * @return string|string[]
     */
    protected function handleUrl(string $url)
    {
        if (strpos($url, ' ') !== false) {
            $url = str_replace(' ', '%20', $url);
        }

        return $url;
    }

    public function httpUpload(string $url, array $files = [], array $form = [], array $query = [])
    {
        $filename = $this->baseName($files['media']);
        $path = $this->handleUrl($files['media']);
        $multipart[] = [
            'name' => 'media',
            'contents' => fopen($path, 'r'),
            'filename' => $filename,
        ];

        $type = isset($query['type']) ? $query['type'] : $query['media_type'];

        $model = Material::builder($this->app->businessId)
            ->where([
                'type' => $type,
                'file_name' => $filename
            ])
            ->first();


        //素材还在3天内直接返回
        if ($model && (($model->updated_at + (86380 * 3)) > time())) {
            return [
                'media_id' => $model->media_id,
                'errmsg' => '无需请求，mediaid未过期!',
                'type' => $type,
                'errcode' => 0
            ];
        }

        $res = $this->request($url, 'POST', ['query' => $query, 'multipart' => $multipart, 'connect_timeout' => 30, 'timeout' => 30, 'read_timeout' => 30]);

        return $this->saveMedia($res, $model, $type, $filename, $path);
    }

    /**
     * 返回文件名
     * @param $filename
     * @return string
     */
    public function baseName($filename): string
    {
        $filename = preg_replace('/^.+[\\\\\\/]/', '', $filename);
        if (mb_strlen($filename) > 50) {
            return mb_substr($filename, -50);
        }
        return $filename;
    }

    /**
     * 附件资源
     * @param string $type
     * @param string $path
     * @param int $attachmentType
     * @return array|\EasyWeChat\Kernel\Support\Collection|mixed|object|\Psr\Http\Message\ResponseInterface|string
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function uploadAttachmentResources(string $type, string $path, int $attachmentType = 1)
    {
        $files = [
            'media' => $path,
        ];
        $query = ['media_type' => $type, 'attachment_type' => $attachmentType];
        return $this->httpUpload('cgi-bin/media/upload_attachment', $files, [], $query);
    }

    protected function saveMedia($res, $model, string $type, string $filename, string $path)
    {
        if (!is_array($res)) {
            $res = json_decode($res, true);
        }

        if ($res['errcode'] === 0) {
            if (!$model) {
                $model = new Material();
            }
            $data = [
                'uniacid' => \YunShop::app()->uniacid,
                'business_id' => $this->app->businessId,
                'type' => $type,
                'file_name' => $filename,
                'file_url' => $path,
                'media_id' => $res['media_id'],
                'updated_at' => time()
            ];
            $model->fill($data);
            if ($model->save()) {
                return $res;
            } else {
                \Log::debug('media_id保存失败', $data);
                throw new AppException('media_id保存失败，具体原因：' . $res['errmsg']);
            }
        } else {
            \Log::debug('素材上传失败,' . $res['errmsg'], ['uniacid' => \YunShop::app()->uniacid, 'business_id' => $this->app->businessId, 'type' => $type, 'file_name' => $filename]);
            throw new AppException('media_id获取失败，具体原因：' . $res['errmsg']);
        }
    }

}
