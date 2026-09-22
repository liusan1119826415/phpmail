<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/2/2
 * Time: 18:07
 */

namespace app\common\models\refund;


use app\common\models\BaseModel;

/**
 * Class PayRefundRecord
 * @property int status
 * @property int pay_type_id
 * @property string pay_sn
 * @property string refund_sn
 * @property double amount
 * @package app\common\models\refund
 */
class PayRefundRecord extends BaseModel
{
    protected $table = 'yz_pay_refund_record';

    protected $guarded = ['id'];

    protected $appends = ['status_name'];

    protected $attributes = [
        'status' => 0,
    ];


    const STATUS_WAIT = 0;//退款处理中
    const STATUS_FINISH = 1;//退款完成
    const STATUS_FAIL = -1;//退款失败


    public function getStatusNameAttribute()
    {
        switch ($this->status) {
            case static::STATUS_FINISH:
                return '退款完成';
                break;
            case static::STATUS_WAIT:
                return '处理中';
                break;
            case static::STATUS_FAIL:
                return '退款失败';
                break;
            default:
                return $this->status?:'---';
        }
    }

    /**
     * 生成唯一转让订单号
     * @return string
     */
    public static function createRequestNo()
    {
        $orderSn = createNo('RT', true);
        while (1) {
            if (!self::where('request_no', $orderSn)->first()) {
                break;
            }
            $orderSn = createNo('RT', true);
        }

        return $orderSn;
    }
}