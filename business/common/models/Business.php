<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2021/9/22
 * Time: 13:37
 */


namespace business\common\models;

use app\common\models\BaseModel;
use \app\backend\modules\member\models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Yunshop\StoreCashier\common\models\Store;

class Business extends BaseModel
{

    public $table = 'yz_work_wechat_platform_crop';
    public $timestamps = true;
    protected $guarded = [];
    protected $appends = [];
    protected $casts = [];


    const IDENTITY_DESC = [
        1 => '员工',
        2 => '管理员',
        3 => '创建人',
        4 => '法人',
        5 => '法人、创建人'
    ];

    const STATUS_NORMAL = 1;//状态正常
    const STATUS_BAN = -1;//状态停用

    public function hasOneMember()
    {
        return $this->hasOne(Member::class, 'uid', 'member_uid');
    }

    public function hasOneStore()
    {
        return $this->hasOne(Store::class, 'id', 'store_id');
    }

}