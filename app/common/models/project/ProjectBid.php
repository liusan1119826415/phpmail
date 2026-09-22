<?php

namespace app\common\models\project;
use app\common\models\BaseModel;
use app\common\models\Member;
use app\common\models\Order;
use Illuminate\Database\Eloquent\SoftDeletes;
use Yunshop\Supplier\common\models\Supplier;

class ProjectBid extends BaseModel
{
    use SoftDeletes;
    protected $table = 'yz_project_bid';



    public function supplier()
    {
        return $this->belongsTo(Supplier::class,'supplier_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class,'project_id');
    }

    public function order()
    {
        return $this->hasMany(Order::class,'bid_id','id');
    }



    public function member()
    {
        return $this->belongsTo(Member::class,'member_id','uid');
    }
    /**
     *  定义字段名
     * 可使
     * @return array */
    public function atributeNames() {
        return [
            'name'=> '项目名称',
            'province_id'=> '省',
            'city_id'=> '市',
            'district_id'=> '区',
            'address_detail'=> '详细地址',
            'project_area'=> '项目面积',
            'project_file'=> '项目文件',
            'bid_type'=>'投标方式',
            'company_name'=> '公司名称',
            'contact_name'=> '联系人姓名',
            'contact_phone'=> '联系人手机',
            'company_address'=> '公司地址',
            'company_type'=> '企业类别',
            'supplier_id'=> '品牌厂家id',
        ];
    }

    /**
     * 字段规则
     * @return array */
    public function rules() {

        return [
            'name' => 'required',
            'province_id'=> 'required',
            'city_id'=> 'required',
            'district_id'=> 'required',
            'address_detail'=> 'required',
            'project_area'=> 'required',
            'project_file'=> 'required',
            'bid_type'=> 'required',
            'company_name'=> 'required',
            'contact_name'=> 'required',
            'contact_phone'=> 'required',
            'company_address'=> 'required',
            'company_type'=> 'required',
            'supplier_id'=> 'required',
        ];
    }





}