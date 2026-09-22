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
use app\common\models\Member;
use business\common\services\SettingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Yunshop\GroupDevelopUser\common\models\GroupMemberLog;

class Staff extends BaseModel
{

    public $table = 'yz_business_staff';
    public $timestamps = true;
    protected $guarded = [];
    protected $appends = ['userid','avatar_mediaid'];
    protected $casts = [];

    const STATUS_UNCONNECT = 0;
    const STATUS_ACTIVE = 1;
    const STATUS_BAN = 2;
    const STATUS_UNACTIVE = 4;
    const STATUS_LEAVE = 5;

    const GENDER_DESC = [
        '0' => '未知',
        '1' => '男',
        '2' => '女',
    ];

    const STATUS_DESC = [
        self::STATUS_UNCONNECT => '未关联',
        self::STATUS_ACTIVE => '已激活',
        self::STATUS_BAN => '已禁用',
        self::STATUS_UNACTIVE => '未激活',
        self::STATUS_LEAVE => '退出企业',
    ];

    use SoftDeletes;

    public function getUseridAttribute()
    {
        return $this->attributes['user_id'];
    }

    public function getAvatarMediaidAttribute()
    {
        return $this->attributes['avatar'];
    }

    public static function business($business_id = 0)
    {
        $business_id = $business_id ?: SettingService::getBusinessId();
        return self::uniacid()->where('business_id', $business_id);
    }

    public function hasManyDepartmentStaff()
    {
        return $this->hasMany(DepartmentStaff::class, 'staff_id', 'id');
    }

    public function hasOneMember()
    {
        return $this->hasOne(Member::class, 'uid', 'uid');
    }

    public function hasOneBusiness()
    {
        return $this->hasOne(Business::class, 'id', 'business_id');
    }

    public function getQyWxStaff($staff, $department_id_arr = [], $department_order_arr = [], $department_leader_arr = [])
    {
        if (is_array($staff)) $staff = (Object)$staff;

        return [
            'userid' => $staff->user_id,
            'name' => $staff->name,
            'alias' => $staff->alias ?: '',
            'mobile' => $staff->mobile,
            'department' => $department_id_arr,
            'order' => $department_order_arr,
            'position' => $staff->position ?: '',
            'gender' => in_array($staff->gender, [1, 2]) ? $staff->gender : 0,
            'email' => $staff->email ?: '',
            'telephone' => $staff->telephone ?: '',
            'is_leader_in_dept' => $department_leader_arr,
            'address' => $staff->address ?: ''
        ];

    }


}
