<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/11/13
 * Time: 17:55
 */

namespace app\backend\modules\integrationPay\models;


use app\common\models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class IntegrationPayWithdraw extends BaseModel
{
    use SoftDeletes;

    protected $table = 'yz_integration_pay_withdraw_record';

    protected $guarded = ['id'];

    protected $hidden = ['uniacid','updated_at','deleted_at'];

    protected $casts = [
        'details' => 'json',
    ];

    protected $dates = ['complete_at'];


    protected $appends = ['status_name', 'settle_type_name'];

    public static function getList($search = [])
    {
        $model = self::uniacid();

        if ($search['merchant_no']) {
            $model->where('merchant_no', $search['merchant_no']);
        }

        if ($search['withdraw_sn']) {
            $model->where('withdraw_sn', $search['withdraw_sn']);
        }

        if ($search['draw_mode']) {
            $model->where('draw_mode', $search['draw_mode']);
        }

        if ($search['settle_type']) {
            $model->where('settle_type', $search['settle_type']);
        }

        if ($search['draw_status']) {
            $model->where('draw_status', $search['draw_status']);
        }


        //操作时间范围
        if ($search['start_time'] && $search['end_time']) {
            if (is_numeric($search['start_time']) && is_numeric($search['start_time'])) {
                $range = [$search['start_time'], $search['end_time']];
            } else {
                $range = [strtotime($search['start_time']), strtotime($search['end_time'])];
            }

            $model->whereBetween('created_at', $range);

        }

        return $model;
    }

    public static function createNo()
    {
        $sn = createNo('UP', true);
        while (1) {
            if (!static::where('withdraw_sn', $sn)->first()) {
                break;
            }
            $sn = createNo('UP', true);
        }
        return $sn;
    }

    public function getSettleTypeNameAttribute()
    {
        if (!$this->settle_type) {
            return '';
        }

        return $this->settle_type == '01' ? '主动提款' : '自动结算';
    }

    public function getStatusNameAttribute()
    {
        if (!$this->draw_status) {
            return '';
        }

        $status_name = $this->draw_status;
        switch ($this->draw_status) {
            case 0:
                $status_name = '提款已受理';
                break;
            case 3:
                $status_name = '提款冻结';
                break;
            case 2:
                $status_name = '提款处理中';
                break;
            case 1:
                $status_name = '提款成功';
                break;
            case -1:
                $status_name = '提款失败';
                break;
        }

        return $status_name;
    }
}