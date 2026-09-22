<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/2/23
 * Time: 15:02
 */

namespace app\backend\modules\refund\services\operation;

use app\common\events\order\AfterOrderRefundSendBackEvent;
use app\common\exceptions\AppException;
use app\common\models\refund\RefundProcessLog;
use app\common\models\refund\ReturnExpress;
use app\common\repositories\ExpressCompany;

class RefundBatchSendBack extends RefundOperation
{
    protected $statusBeforeChange = [self::WAIT_RETURN_GOODS];
    protected $statusAfterChanged = self::WAIT_RECEIVE_RETURN_GOODS;
    protected $name = '用户发货';
    protected $timeField = 'return_time'; //用户退货时间

    protected $expressInfo;

    protected function afterEventClass()
    {
        return new AfterOrderRefundSendBackEvent($this);
    }

    protected function updateBefore()
    {
        $send_info = $this->getRequest()->input('send_info');

//        $send_info =  [
//            ['express_sn' => '2323123', 'express_company_code' => 'SF', 'order_goods_ids'=> '918'],
//        ];


        $expressCompanies = \app\common\repositories\ExpressCompany::create();

        $expressInfo = [];
        foreach ($send_info as $info) {

            if (!is_array($info) || empty($info['express_sn']) || empty($info['address'])) {
                continue;
            }

            $order_goods_ids = is_array($info['order_goods_ids']) ? $info['order_goods_ids']: explode(',', $info['order_goods_ids']);

            $pack_goods = $this->refundOrderGoods->whereIn('order_goods_id', $order_goods_ids)->map(function ($refundGoods) {
                return [
                    'order_goods_id' => $refundGoods->order_goods_id,
                    'title' => $refundGoods->goods_title,
                    'goods_option_title' => $refundGoods->goods_option_title,
                    'thumb' => $refundGoods->goods_thumb,
                    'total' => $refundGoods->refund_total,
                ];
            })->toArray();

            $expressInfo[] =  [
                'refund_id' => $this->id,
                'express_sn' => $info['express_sn'],
                'express_company_name' => array_get($expressCompanies->where('value', $info['express_company_code'])->first(), 'name', '其他快递'),
                'express_code' => $info['express_company_code'],
                'images' => $info['images']?:'',
                'address' => $info['address']?:'',
                'contacts_info' => json_encode(['contacts_name'=> $info['contact'],'mobile'=> $info['mobile'], 'plane' => $info['plane']]),
                'pack_goods' => json_encode($pack_goods),
                'created_at' => time(),
                'updated_at' => time()
            ];
        }


        if (!$expressInfo) {
            throw new AppException('无物流信息，请填写快递信息');
        }

        //dd($expressInfo);

        $this->expressInfo = $expressInfo;

        ReturnExpress::insert($expressInfo);

    }

    protected function updateAfter()
    {

    }

    protected function writeLog()
    {
        $detail = [
            '寄回方式：多包裹寄回',
            '包裹数量：'.count($this->expressInfo),
        ];
        $processLog = RefundProcessLog::logInstance($this, RefundProcessLog::OPERATOR_MEMBER);
        $processLog->setAttribute('operate_type', RefundProcessLog::OPERATE_USER_SEND_BACK);
        $processLog->saveLog($detail);
    }
}