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
use business\common\services\SettingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends BaseModel
{

    public $table = 'yz_business_department';
    public $timestamps = true;
    protected $guarded = [];
    protected $appends = [];
    protected $casts = [];

    use SoftDeletes;

    public static function business($business_id = 0)
    {
        $business_id = $business_id ?: SettingService::getBusinessId();
        return self::uniacid()->where('business_id', $business_id);
    }

    /*
     * 获取当前部门的所有上级部门ID
     */
    public function getAllDepartmentParentId($department_id_arr, $department_list = [], $business_id = 0)
    {
        $business_id = $business_id ?: SettingService::getBusinessId();
        $department_list = $department_list ?: self::where('business_id', $business_id)->get();
        $this_parent_id_arr = $department_list->whereIn('id', $department_id_arr)->pluck('parent_id')->toArray();
        $parent_id_arr = $this_parent_id_arr = array_values(array_unique(array_filter($this_parent_id_arr)));

        if (!$parent_id_arr) {
            return [];
        }

        do {
            $this_parent_id_arr = $department_list->whereIn('id', $this_parent_id_arr)->pluck('parent_id')->toArray();
            $this_parent_id_arr = array_values(array_unique(array_filter($this_parent_id_arr)));
            $parent_id_arr = array_merge($parent_id_arr, $this_parent_id_arr);
        } while ($this_parent_id_arr);

        return array_values(array_unique(array_filter($parent_id_arr)));
    }


    /*
     * 获取当前部门的所有下级部门ID
     */
    public function getAllDepartmentSubId($department_id_arr, $department_list = [], $business_id = 0)
    {
        $business_id = $business_id ?: SettingService::getBusinessId();
        $department_list = $department_list ?: self::where('business_id', $business_id)->get();
        $this_sub_id_arr = $department_list->whereIn('parent_id', $department_id_arr)->pluck('id')->toArray();
        $sub_id_arr = $this_sub_id_arr = array_values(array_unique(array_filter($this_sub_id_arr)));

        if (!$sub_id_arr) {
            return [];
        }

        do {
            if ($this_sub_id_arr = $department_list->whereIn('parent_id', $this_sub_id_arr)->pluck('id')->toArray()) {
                $sub_id_arr = array_merge($sub_id_arr, $this_sub_id_arr);
            }
        } while ($this_sub_id_arr);

        return array_values(array_unique(array_filter($sub_id_arr)));
    }


    /**
     * 获取部门员工
     * @return void
     */
    public function getDepartmentMember()
    {
        /**
         * @var Department $department_mo
         */
        $department_mo = $this->business()->select(['id', 'name', 'parent_id', 'wechat_department_id']);
        $department_mo->with(['hasMayDepartmentStaff' => function ($q) {
            $q->where('business_id', SettingService::getBusinessId())->select(['id', 'department_id', 'staff_id'])->with(['hasOneStaff' => function ($q) {
                $q->where('business_id', SettingService::getBusinessId())->select(['id', 'name', 'user_id', 'uid', 'avatar'])->where('disabled', 0);
            }
            ])->groupBy('department_id')->groupBy('staff_id');
        }
        ]);
        return $department_mo->get()->toArray();
    }

    public function getWechatBusinessDepartment($business_id)
    {
        return self::where('business_id', $business_id)->where('wechat_department_id', '<>', 0)->get();
    }

    public function hasOneParentDepartment()
    {
        return $this->hasOne(self::class, 'id', 'parent_id');
    }

    public function hasManySubDepartment()
    {
        return $this->hasMany(self::class, 'parent_id', 'id');
    }

    public function hasMayDepartmentStaff()
    {
	    return $this->hasMany(DepartmentStaff::class, 'department_id', 'id');
    }

}
