<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/8/9
 * Time: 15:08
 */

namespace app\common\modules\refund\optional;


trait OptionalTypesTrait
{
    //前端售后申请选项
    public function getOptionalTypes()
    {
        $operationsSettings = $this->getSupportApplyTypes();
        $operations = array_map(function ($operationName) {
            /**
             * @var OptionalTypesAbstract $operation
             */
            $operation = new $operationName($this->getOrder(), $this);
            if (!$operation->enable()) {
                return null;
            }
            $result['name'] = $operation->getName();
            $result['value'] = $operation->getValue();
            $result['desc'] = $operation->getDesc();
            $result['icon'] = $operation->getIcon();
            $result['service_status_data'] = $operation->getStatusData();
            $result['reasons'] = [
                'not_received' => $operation->notReceived(),
                'received' => $operation->received()
            ];

            return $result;
        }, $operationsSettings);

        $operations = array_filter($operations);
        return array_values($operations) ?: [];
    }


    protected function getSupportApplyTypes()
    {
       return [
           RefundOnly::class,
//           RefundReturnGoods::class,
//           ExchangeGoods::class,



       ];
    }
}