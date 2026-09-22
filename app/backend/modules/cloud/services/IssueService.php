<?php


namespace app\backend\modules\cloud\services;

use app\common\exceptions\ShopException;
use app\common\models\project\Issue;
use app\common\models\project\IssueOptions;

class IssueService
{


    public function getList($search)
    {

        $query = Issue::select("id", "member_id", "issue_id", "issue_parent", "content","is_reply", "created_at")
            ->with([
                'Member' => function ($query) {
                    $query->select("uid", "nickname", "avatar");
                },
                'Issue' => function ($query) {
                    $query->select("id", "title");
                }
            ]);

        if ($search['issue_id']) {
            $query->where('issue_id', $search['issue_id']);
        }

        if ($search['issue_parent']) {
            $parentId = $search['issue_parent'];

            $query->whereJsonContains('issue_parent', $parentId);
        }
        if(isset($search['is_reply']) && ($search['is_reply'] !== '' || $search['is_reply'] === 0)){
            $query->where('is_reply', $search['is_reply']);
        }

        $query->orderBy('created_at', 'desc');

        $data = $query->paginate(20);

        // 收集所有 issue_parent 中的 ID
        $allParentIds = collect();

        // 第一次循环：收集所有需要查询的 ID
        $data->each(function ($item) use (&$allParentIds) {
            $parentIds = $item->issue_parent;

            if (!empty($parentIds)) {
                $allParentIds = $allParentIds->merge($parentIds);
            }
        });


        // 批量查询所有关联的 options
        $allOptions = IssueOptions::whereIn('id', $allParentIds->unique())
            ->select('id', 'title')
            ->get()
            ->keyBy('id'); // 按键为 ID 的集合，便于查找

        // 第二次循环：为每个项分配关联数据
        $data->getCollection()->transform(function ($item) use ($allOptions) {
            $parentIds = $item->issue_parent;

            if (!empty($parentIds)) {
                $item->issue_parent_options = collect($parentIds)
                    ->map(function ($id) use ($allOptions) {
                        return $allOptions->get($id);
                    })
                    ->filter(); // 过滤掉 null 值
            } else {
                $item->issue_parent_options = collect([]);
            }

            return $item;
        });

        return $data;
    }

    public function getDetail($id)
    {
        $data = Issue::select("id", "member_id", "issue_id", "issue_parent", "content", "thumb_url", "file_url","is_reply","reply_content", "created_at")
            ->with([
                'Member' => function ($query) {
                    $query->select("uid", "nickname", "avatar");
                },
                'Issue' => function ($query) {
                    $query->select("id", "title");
                }
            ])
            ->find($id);

        // 如果找到了数据且 issue_parent 不为空
        if ($data && $data->issue_parent) {
            // 解析 JSON 数组
            $parentIds = $data->issue_parent;

            // 查询关联的 issue_options
            if (is_array($parentIds) && count($parentIds) > 0) {
                $options = IssueOptions::whereIn('id', $parentIds)
                    ->select('id', 'title') // 根据需要选择字段
                    ->get();

                // 将查询结果附加到数据中
                $data->issue_parent_options = $options;
            } else {
                $data->issue_parent_options = collect([]);
            }
        }

        return $data;
    }

    public function delete($id)
    {
        $issue = Issue::find($id);
        if (!$issue) {
            throw new ShopException('反馈问题不存在');
        }
        $issue->delete();
        return true;
    }

    //处理回复
    public function handleReply($id, $content)
    {
        try {
            $issue = Issue::with(['member', 'issue'])->find($id);

            if (!$issue) {
                throw new ShopException('反馈问题不存在');
            }

            // 更新回复内容
            $issue->reply_content = $content;
            $issue->is_reply = 1;
            $issue->save();

            // 发送消息给会员 - 优化话术
            $this->sendReplyNotification($issue, $content);

            return true;
        } catch (\Exception $e) {
            \Log::error('处理问题回复失败', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw new ShopException("处理问题回复失败");
        }
    }

    /**
     * 发送回复通知（优化话术）
     */
    protected function sendReplyNotification($issue, $replyContent)
    {
        $memberIds = [$issue->member_id];
        $sub_type = 4;
        $title = '问题反馈已回复';

        // 优化后的通知内容
        $content = $this->formatNotificationContent($issue, $replyContent);

        $options = [
            'related_id' => $issue->id,
            'type' => 'issue_reply',
            'reply_time' => now()->toDateTimeString()
        ];

        app('notification')->sendBatch($memberIds, $sub_type, $title, $content, $options);
        return true;
    }

    /**
     * 格式化通知内容（多种话术模板）
     */
    protected function formatNotificationContent($issue, $replyContent)
    {
        // 根据问题类型选择不同的话术模板
        $templates = [
            // 模板1：标准回复
            'default' => "尊敬的{user}，您好！\n\n"
                . "您于{submit_time}反馈的【{issue_title}】问题，我们已经仔细查看并给予回复：\n\n"
                . "【您的反馈内容】\n{original_content}\n\n"
                . "【我们的回复】\n{reply_content}\n\n"
                . "感谢您的反馈，如果您还有其他疑问，请随时联系我们。\n"
                . "祝您生活愉快！\n\n"
                . "{platform_name}团队\n{reply_time}",

            // 模板2：简洁版
            'simple' => "亲爱的{user}，您好！\n\n"
                . "您反馈的问题【{issue_title}】已得到回复：\n{reply_content}\n\n"
                . "感谢您的支持！",

            // 模板3：带有解决方案
            'solution' => "尊敬的用户，您好！\n\n"
                . "关于您反馈的【{issue_title}】问题，我们已经处理完毕：\n\n"
                . "▪ 问题描述：{original_content}\n"
                . "▪ 解决方案：{reply_content}\n\n"
                . "如问题仍未解决，欢迎继续反馈。",
        ];

        // 获取模板数据
        $data = [
            'user' => $issue->member->nickname ?? '用户',
            'submit_time' => $issue->created_at->format('Y-m-d H:i'),
            'issue_title' => $issue->Issue->title ?? '反馈问题',
            'original_content' => mb_substr($issue->content, 0, 200) . (mb_strlen($issue->content) > 200 ? '...' : ''),
            'reply_content' => $replyContent,
            'platform_name' => '帮米',
            'reply_time' => now()->format('Y-m-d H:i'),
        ];

        // 根据回复内容长度选择模板
        $templateKey = "default";

        // 使用模板替换变量
        $template = $templates[$templateKey];
        $content = $this->replaceTemplateVariables($template, $data);

        return $content;
    }

    /**
     * 替换模板变量
     */
    protected function replaceTemplateVariables($template, $data)
    {
        foreach ($data as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        return $template;
    }
}
