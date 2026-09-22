<?php

namespace app\framework\EasyWechat\Work;

class ExternalContactMessage extends \EasyWeChat\Work\ExternalContact\MessageClient
{
    /**
     * 朋友圈任务推送
     * @param array $msg
     * @return array|\EasyWeChat\Kernel\Support\Collection|object|\Psr\Http\Message\ResponseInterface|string
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function moments(array $msg)
    {
        return $this->httpPostJson('/cgi-bin/externalcontact/add_moment_task', $msg);
    }


    /**
     * 获取任务创建结果
     * @param string $jobid
     * @return array|\EasyWeChat\Kernel\Support\Collection|object|\Psr\Http\Message\ResponseInterface|string
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getMomentTaskResult(string $jobid)
    {
        return $this->httpGet('/cgi-bin/externalcontact/get_moment_task_result', compact('jobid'));
    }

    /**
     * 获取客户朋友圈企业发表的列表
     * @param string $momentId
     * @param int $limit
     * @param string|null $cursor
     * @return array|\EasyWeChat\Kernel\Support\Collection|object|\Psr\Http\Message\ResponseInterface|string
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getMomentTask(string $momentId, int $limit = 10, string $cursor = null)
    {
        $data = [
            'moment_id' => $momentId,
            'limit' => $limit,
        ];
        if ($cursor) {
            $data['cursor'] = $cursor;
        }
        return $this->httpPostJson('/cgi-bin/externalcontact/get_moment_task', $data);
    }


    /**
     * 获取客户朋友圈的互动数据
     * @param string $momentId
     * @param string $userid
     * @return array|\EasyWeChat\Kernel\Support\Collection|object|\Psr\Http\Message\ResponseInterface|string
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getMomentComments(string $momentId, string $userid)
    {
        return $this->httpPostJson('/cgi-bin/externalcontact/get_moment_comments', [
            'moment_id' => $momentId,
            'userid' => $userid,
        ]);
    }

}
