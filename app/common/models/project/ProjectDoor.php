<?php

namespace app\common\models\project;
use app\common\models\BaseModel;
use app\common\models\Member;
use Illuminate\Database\Eloquent\SoftDeletes;
use Yunshop\Supplier\common\models\Supplier;

class ProjectDoor extends BaseModel
{

    protected $table = 'yz_project_door';



    public function project()
    {
        return $this->belongsTo(Project::class,'project_id');
    }

    /**
     *  定义字段名
     * 可使
     * @return array */
    public function atributeNames() {
        return [
            'project_id'=> '项目id',
            'province_id'=> '省',
            'city_id'=> '市',
            'district_id'=> '区',
            'address_detail'=> '详细地址',
            'project_area'=> '项目面积',
            'service_id'=> '服务类目',
            'service_type'=>'服务类型',
            'door_time'=>'上门时间',
            'service_day'=>'服务天数',
            'contact_name'=> '联系人姓名',
            'contact_phone'=> '联系人手机',

        ];
    }

    /**
     * 字段规则
     * @return array */
    public function rules() {

        return [
            'project_id'=> 'required',
            'province_id'=> 'required',
            'city_id'=> 'required',
            'district_id'=> 'required',
            'address_detail'=> 'required',
            'project_area'=> 'required',
            'service_id'=> 'required',
            'service_type'=>'required',
            'door_time'=>'required',
            'service_day'=>'required',
            'contact_name'=> 'required',
            'contact_phone'=> 'required',
        ];
    }





}