<?php

namespace app\common\modules\express\expressCompany;

use app\common\exceptions\AppException;

class SjtLogistics implements Logistics
{

    public function getTraces($comCode, $expressSn, $orderSn, $phoneLastFour)
    {
        if (!app('plugins')->isEnabled('service-provider-connect')) {
            return $this->errorMsg('数据通系统对接插件未安装！');
        }
        try {
            $client = new \Yunshop\ServiceProviderConnect\common\ClientRequest();
        } catch (\Exception $e) {
            return $this->errorMsg($e->getMessage());
        }
        $client->setMethod('/api/open/plugins/apigather/package_query');
        $client->setAllParameter([
            'number' => $expressSn,
            'company_code' => $comCode,
        ]);
        $result = $client->post();
        if (!$result['status']) {
            return $this->errorMsg($result['msg']);
        }

        $other_res = $result['data'];

        $exp_info = $this->format($other_res);
        $exp_info['data'] = array_reverse($exp_info['data']);

        return $exp_info;
    }

    private function format($response)
    {
        $result = [];
        foreach ($response['list'] as $trace) {
            $result['data'][] = [
                'time' => $trace['time'],
                'ftime' => $trace['time'],
                'context' => $trace['status'],
                'location' => null,
            ];
        }
        $result['state'] = $response['deliverystatus'];
        return $result;
    }


    protected function errorMsg($msg)
    {
        return [
            'data' => [
                [
                    'context' => '数据通错误信息：' . $msg
                ]
            ]
        ];
    }
}
