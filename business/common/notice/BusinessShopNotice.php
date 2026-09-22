<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2022/8/8
 * Time: 10:01
 */

namespace business\common\notice;


use business\common\models\MessageNotice;

class BusinessShopNotice extends BusinessMessageNoticeBase
{
    public function getPluginName()
    {
        return '商城基础';
    }

    public function getPlugin()
    {
        return 'shop';
    }

    public function create($data = [])
    {
        $this->saveNotice($data['user_ids'], $data['param'], $data['html']);
    }

    public function showBody()
    {

        $notice= $this->getMessageNotice();

        $body = [
            'head' => $notice->param['head'],
            'title' =>$notice->param['title'],
            'content' =>  $notice->html?:'',
            'notice_time'=>  $notice->created_at ? $notice->created_at->format('Y-m-d H:i:s'): date('Y-m-d H:i:s', time()),
        ];
        return $body;
    }

    public function webSocketMessage(MessageNotice $notice)
    {

        $msg['jump_url'] = yzBusinessFullUrl('login');
        $msg['creator_name'] = $notice->getSendStaff()->name;
        $msg['notice_time'] = $notice->created_at ? $notice->created_at->format('Y-m-d H:i:s'): date('Y-m-d H:i:s', time());
        $msg['content'] = $notice['html'];
        $msg['type'] = 'shop';
        $msg['code'] = $this->getPlugin();


        return $msg;
    }

    public function getAllType()
    {
        return [];
    }
}
