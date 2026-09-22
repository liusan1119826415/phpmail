<?php
/**
 * Created by PhpStorm.
 *
 * User: king/QQ：995265288
 * Date: 2018/5/15 上午10:00
 * Email: livsyitian@163.com
 */

namespace app\backend\modules\income\models;


use app\common\models\BaseModel;
use app\common\models\user\User;
use app\common\scopes\UniacidScope;


class ChangeAmountLog extends BaseModel
{
    protected $table = 'yz_member_income_change_amount_log';
    protected $fillable = [];
    protected $guarded = [];

    public function User()
    {
        return $this->hasOne(User::class, 'uid', 'operator_id');
    }
}
