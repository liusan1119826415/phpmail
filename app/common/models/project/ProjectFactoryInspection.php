<?php

namespace app\common\models\project;
use app\common\models\BaseModel;
use app\common\models\Member;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectFactoryInspection extends BaseModel
{
    use SoftDeletes;
    protected $table = 'yz_project_factory_inspection';



    public function member()
    {
        return $this->belongsTo(Member::class,'member_id','uid');
    }

    public function project()
    {
        return $this->belongsTo(Project::class,'project_id','id');
    }

    /**
     *  定义字段名
     * 可使
     * @return array */
    public function atributeNames() {
        return [
            'name'=> '项目名称',
            'applicant_name'=> '申请人姓名',
            'applicant_phone'=> '申请人手机',
            'inspection_mode'=> '考察模式',
            'scheduled_inspection_date'=> '计划考察日期',
            'scheduled_arrival_date'=> '计划到达日期',

//            'company_name'=> '公司名称',
//            'contact_name'=> '联系人姓名',
//            'contact_phone'=> '联系人手机',
//            'company_type'=> '企业类别',
            'supplier_id'=> '品牌厂家id',
            'projects_visit'=> '参观项目',
            'travel_mode'=> '出行方式',

        ];
    }

    /**
     * 字段规则
     * @return array */
    public function rules() {

        return [
            'name'=> 'required',
            'applicant_name'=> 'required',
            'applicant_phone'=> 'required',
            'inspection_mode'=> 'required',
            'scheduled_inspection_date'=> 'required',
            'scheduled_arrival_date'=> 'required',
//            'company_name'=> 'required',
//            'contact_name'=> 'required',
//            'contact_phone'=> 'required',
//            'company_type'=> 'required',
            'supplier_id'=> 'required',
            'projects_visit'=> 'required',
            'travel_mode'=> 'required',
        ];
    }





}