<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2022/8/3
 * Time: 16:40
 */

namespace business\common\notice;


use business\common\models\MessageNotice;

abstract class BusinessMessageNoticeBase
{

    /**
     * @var MessageNotice
     */
    protected $messageNotice;

    protected $noticeIds;

    /**
     * @return mixed
     */
    final public function getNoticeIds()
    {
        return $this->noticeIds;
    }

    /**
     * @param mixed $noticeIds
     */
    final public function setNoticeIds($noticeIds): void
    {
        $this->noticeIds = $noticeIds;
    }

    final public function getMessageNotice()
    {
        return $this->messageNotice;
    }

    final public function setMessageNotice(MessageNotice $messageNotice)
    {
        $this->messageNotice = $messageNotice;
    }

    /**
     * @param array $userIdArray
     * @param array $param
     * @param string|mixed $html
     */
    protected function saveNotice(array $userIdArray, array $param, $html = '')
    {

        $createData = [
            'param' => $param,
            'html' => $html ?: '',
        ];

        $createData = array_merge($createData,$this->getMessageNotice()->getAttributes());

        $noticeIds = [];
        foreach ($userIdArray as $user_id) {
            $createData['recipient_id'] = $user_id;
            $notice = MessageNotice::create($createData);
            if ($notice) {
                $noticeIds[] = $notice->id;
            }
        }
        $this->setNoticeIds($noticeIds);


        //这里可以设置最后一个保存的消息记录
//        if($notice){
//            $this->setMessageNotice($notice);
//        }

        $this->sendSocket($noticeIds);

        //return $noticeIds;
    }


    final protected function sendSocket($noticeIds)
    {

        //这里可以直接让方法返回，
        $notices = MessageNotice::whereIn('id', $noticeIds)->get();

//        \Log::debug('-----webSocketNotice-----',$notices->pluck('id')->all());
        $signUserIds = $notices->map(function (MessageNotice $notice) {
            return $notice->hasOneRecipient->uid ?: null;
        })->filter()->unique()->values()->toArray();


        if (empty($signUserIds)) {
            return;
        }

        $firstNotice = $notices->first();

        $msg = $this->webSocketMessage($firstNotice);

        if (empty($msg)) {
            return;
        }

        if (!isset($msg['logo'])) {
            $shopSet = \Setting::get('shop.shop');
            $msg['logo'] = yz_tomedia($shopSet['logo']);
        }

        if (!isset($msg['notice_time'])) {
            $msg['notice_time'] = date('Y-m-d H:i:s', time());
        }

//        \Log::debug('-----webSocketNotice-----',$signUserIds);
//        \Log::debug('-----webSocketNotice-----',$msg);

        $this->webSocketNotice($signUserIds, $msg);
    }


    /**
     * 发送webSocket消息通知
     * @param $user_ids array 会员ID数组
     * @param $content mixed 文本内容
     * @param string $type
     */
    final protected function webSocketNotice($user_ids, $content, $type = 'businessMessageNotice')
    {
        \app\process\InnerSocket::send($user_ids, $content, $type);

    }


    public function sendStaff()
    {
        return $this->messageNotice->hasOneCreator;
    }




    //考虑到需要删除
    public function delete(){}


    /**
     * @param MessageNotice $notice
     * @return array [code,logo,jump_url,creator_name,notice_time,content,type]
     */
    abstract public function webSocketMessage(MessageNotice $notice);

    /**
     * @return mixed
     */
    abstract public function showBody();

    /**
     * @param array $data
     */
    abstract public function create($data);

    /**
     * 保持与服务容器绑定的标识一致
     * @return string
     */
    abstract public function getPlugin();

    /**
     * @return string
     */
    abstract public function getPluginName();


    /**
     * 消息类型集合
     * @return array
     */
    abstract public function getAllType();


}
