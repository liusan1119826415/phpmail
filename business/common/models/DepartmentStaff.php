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

class DepartmentStaff extends BaseModel
{

    public $table = 'yz_business_department_staff';
    public $timestamps = true;
    protected $guarded = [];
    protected $appends = [];
    protected $casts = [];

    public function business($business_id = 0)
    {
        $business_id = $business_id ?: SettingService::getBusinessId();
        return self::uniacid()->where('business_id', $business_id);
    }

    public function hasOneDepartment()
    {
        return $this->hasOne(Department::class, 'id', 'department_id');
    }

    public function hasOneStaff()
    {
        return $this->hasOne(Staff::class, 'id', 'staff_id');
    }


}