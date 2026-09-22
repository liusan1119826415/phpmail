<?php

namespace business\common\services;

use app\common\exceptions\AppException;
use app\common\facades\EasyWeChat;
use business\common\models\Material;
use EasyWeChat\Factory;

class MaterialService
{
    static $current;

    /**
     * @var \EasyWeChat\Work\Application
     */
    protected $agent_app;

    protected $nickname;
    protected $business_id;
    protected $filterName = false;
    const MEDIA_UPLOAD = "cgi-bin/media/upload";

    private function __construct($business_id)
    {
        $business_id or $business_id = SettingService::getBusinessId();
        if (!$business_id) {
            throw new AppException('企业ID参数缺失！');
        }
        $this->business_id = $business_id;
    }

    /**
     * @param $business_id
     * @param $app
     * @return static
     * @throws AppException
     */
    public static function current($business_id = 0, $app = null)
    {
        if (static::$current) {
            return static::$current;
        }

        static::$current = new static($business_id);

        if ($app) {
            static::$current->setApp($app);
        } else {
            $config = SettingService::getQyWxSetting($business_id);

            static::$current->setApp(EasyWeChat::work([
                'corp_id' => $config['corpid'],
                'agent_id' => $config['agentid'],
                'secret' => $config['contact_secret'],
            ], [
                'businessId' => $business_id
            ]));
        }

        return static::$current;
    }

    public function setApp($app)
    {
        $this->agent_app = $app;
    }

    // 设置文本昵称
    public function setNickname($nickname)
    {
        $this->nickname = $nickname;
    }

    public function filterName()
    {
        $this->filterName = true;
    }

    /**
     * 返回文件名
     * @param $filename
     * @return string
     */
    public static function baseName($filename): string
    {
        return preg_replace('/^.+[\\\\\\/]/', '', $filename);
    }

    public function handleContent($content_list)
    {
        foreach ($content_list as &$v) {
            switch ($v['type']) {
                case 'text':
                    // PC后台编辑素材内容时换行有以下三种形式
                    $str = str_replace('<div><br></div>', "\n", html_entity_decode($v['news']));
                    $str = str_replace('<br>', "\n", $str);
                    $v['news'] = strip_tags(str_replace('<div>', "\n", $str));

                    if ($this->filterName) {
                        $v['news'] = str_replace('客户昵称', '', $v['news']);
                    }

                    // 传入客户昵称或群聊昵称则替换
                    if ($this->nickname) {
                        $v['news'] = str_replace('客户昵称', $this->nickname, $v['news']);
                    }
                    break;
                case 'image':
                    $v['media_id'] = $this->getMediaId($v['type'], $v['link_img']);
                    break;
                case 'link':

                    break;
                case 'applet':
                    $v['media_id'] = $this->getMediaId('image', $v['link_img']);
                    if ($v['page']) {
                        if (($start = strpos($v['page'], '?')) !== false) {
                            $v['page'] = substr_replace($v['page'], '.html?', $start, 1);
                        } else {
                            $v['page'] .= '.html';
                        }
                    }
                    break;
                case 'video':
                    $v['media_id'] = $this->getMediaId($v['type'], $v['link_video']);
                    break;
                case 'file':
                    $v['media_id'] = $this->getMediaId($v['type'], $v['link_file']);
                    break;
            }
        }

        return $content_list;
    }

    public function getMediaId($type, $url)
    {
        $res = $this->agent_app->media->upload($type, $url);
        return $res['media_id'];
    }
}
