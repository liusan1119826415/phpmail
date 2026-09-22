<?php

namespace app\common\modules\pcnotice;


use app\common\models\project\Notification;


class NotificationService
{
    /**
     * 发送单个通知
     *
     * @param string $templateKey 模板key
     * @param array $data 模板变量数据
     * @param array $options 额外选项
     * @return Notification|null
     */
    public function send(int $member_id,string $notice_type,$sub_type,string $templateKey, array $data = [], array $options = [])
    {
        try {


            // 获取模板配置
            $template = Template::getTemplate($templateKey);
            if (empty($template)) {
                throw new \Exception("模板{$templateKey}不存在");
            }

            // 验证必要变量
            $this->validateVariables($template, $data);

            // 渲染通知内容
            $content = $this->renderContent($template['content'], $data);
            $title = $this->renderContent($template['title'], $data);

            // 创建通知记录
            $notification = Notification::create([
                'member_id' => $member_id,
                'template_key' => $templateKey,
                'title' => $title,
                'content' => $content,
                'notice_type'=>$notice_type,
                'related_id' => $options['related_id'] ?? null,
                'sub_type' => $sub_type

            ]);

//            // 发送到其他渠道
//            $this->dispatchToChannels($notification, $options['channels'] ?? ['system']);

            return $notification;

        } catch (\Exception $e) {
            \Log::error("发送通知失败: " . $e->getMessage(), [
                'user_id' => $user->id ?? null,
                'template' => $templateKey,
                'data' => $data
            ]);
            return null;
        }
    }

    /**
     * 批量发送通知
     *
     * @param array $users 用户ID数组或用户对象数组
     * @param string $templateKey 模板key
     * @param array $data 模板变量数据
     * @param array $options 额外选项
     * @return array 成功发送的通知ID数组
     */
    public function sendBatch(array $member_ids, string $templateKey, array $data = [], array $options = [])
    {
        $successIds = [];

        foreach ($member_ids as $member_id) {
            $notification = $this->send($member_id, $templateKey, $data);
            if ($notification) {
                $successIds[] = $notification->id;
            }
        }

        return $successIds;
    }

    /**
     * 发送订单类通知
     *
     * @param int|User $user 用户ID或用户对象
     * @param string $orderNo 订单编号
     * @param string $templateType 模板类型
     * @param array $extraData 额外数据
     * @return Notification|null
     */
    public function sendOrderNotification($user, string $orderNo, string $templateType, array $extraData = [])
    {
        $data = array_merge(['order_no' => $orderNo], $extraData);

        // 根据订单类型自动选择模板
        $templateKey = $this->determineOrderTemplate($templateType, $extraData['order_type'] ?? 'normal');

        return $this->send($user, $templateKey, $data, [
            'related_id' => $orderNo
        ]);
    }


    /**
     * 验证模板变量
     */
    protected function validateVariables(array $template, array $data)
    {
        foreach ($template['variables'] as $var) {
            if (!array_key_exists($var, $data)) {
                throw new \Exception("缺少必要变量: {$var}");
            }
        }
    }

    /**
     * 渲染内容模板
     */
    protected function renderContent(string $content, array $data): string
    {
        return preg_replace_callback('/\{\{(\w+)\}\}/', function ($matches) use ($data) {
            return $data[$matches[1]] ?? $matches[0];
        }, $content);
    }

    /**
     * 分发到其他渠道
     */
    protected function dispatchToChannels(Notification $notification, array $channels)
    {
        foreach ($channels as $channel) {
            try {
                switch ($channel) {
                    case 'email':
                        $this->sendEmail($notification);
                        break;
                    case 'sms':
                        $this->sendSms($notification);
                        break;
                    case 'app_push':
                        $this->sendAppPush($notification);
                        break;
                }
            } catch (\Exception $e) {
                Log::error("通知渠道{$channel}发送失败: " . $e->getMessage(), [
                    'notification_id' => $notification->id
                ]);
            }
        }
    }

    /**
     * 发送邮件通知
     */
    protected function sendEmail(Notification $notification)
    {
        // 实际邮件发送逻辑
        // Mail::to($notification->user->email)->send(new NotificationMail($notification));
    }

    /**
     * 发送短信通知
     */
    protected function sendSms(Notification $notification)
    {
        // 实际短信发送逻辑
        // SmsService::send($notification->user->phone, $notification->content);
    }

    /**
     * 发送APP推送
     */
    protected function sendAppPush(Notification $notification)
    {
        // 实际APP推送逻辑
        // PushService::send($notification->user_id, $notification->title, $notification->content);
    }

    /**
     * 确定订单模板
     */
    protected function determineOrderTemplate(string $templateType, string $orderType): string
    {
        $templates = [
            'submit' => Template::PURCHASE_ORDER['SUBMIT'],
            'payment' => Template::PURCHASE_ORDER['DEPOSIT_PAID'],
            'delivery' => Template::PURCHASE_ORDER['DELIVERY'],
            // 其他类型映射...
        ];

        return $templates[$templateType] ?? $templateType;
    }
}