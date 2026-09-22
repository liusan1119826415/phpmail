<?php

namespace app\common\models\project;
use app\common\models\BaseModel;
use app\common\models\Member;
use app\common\models\Order;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends BaseModel
{
    use SoftDeletes;
    protected $table = 'yz_my_project';

    public $appends=['status_name','report_status_name','bid_status_name','factory_status_name'];
    public $fillable = ["uniacid","member_id","activate","price","report_status","name","status","province_id","city_id",'district_id',"address_detail","contact_name","phone"];


    public function floors()
    {
        return $this->hasMany(Floors::class,"project_id","id");
    }

    public function floorActivate()
    {
        return $this->hasOne(Floors::class,"project_id","id")->where('activate',1);
    }

    //关联报备
    public function report()
    {
        return $this->hasOne(ProjectReport::class,'project_id','id');
    }

    //关联投标
    public function bid()
    {
        return $this->hasOne(ProjectBid::class,'project_id','id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class,'member_id','uid');
    }



    public function getStatusNameAttribute()
    {

        if($this->attributes['activate'] == 1){
            $name = '正在配置中';
        }elseif($this->attributes['order_status'] == 0){
            $name = "未下单";
        }elseif($this->attributes['order_status'] == 1){
            $name = "已下单";
        }

        /*switch ($this->attributes['order_status']) {
            case 0:
                $name = '正在配置中';
                break;
            case 1:
                $name = '已下单';
                break;
            default:
                $name = '正在配置中';
        }*/
        return $name;
    }



    public function getReportStatusNameAttribute()
    {
        switch ($this->attributes['report_status']) {
            case 0:
                $name = '未报备';
                break;
            case 1:
                $name = '申请中';
                break;
            case 2:
                $name = '已报备';
                break;
            case 3:
                $name = '申请未通过';
                break;
            default:
                $name = '未报备';
        }
        return $name;
    }


    public function order()
    {
       return $this->belongsTo(Order::class,'order_id','id');
    }



    public function getBidStatusNameAttribute()
    {
        switch ($this->attributes['bid_status']) {
            case 1:
                $name = '未申请';
                break;
            case 2:
                $name = '申请中';
                break;
            case 3:
                $name = '未通过';
                break;
            case 4:
                $name = '投标中';
                break;
            case 5:
                $name = '投标结束';
                break;
            default:
                $name = '未投标';
        }
        return $name;
    }


    public function getFactoryStatusNameAttribute()
    {
        switch ($this->attributes['factory_status']) {
            case 1:
                $name = '未申请';
                break;
            case 2:
                $name = '申请中';
                break;
            case 3:
                $name = '申请成功';
                break;
            case 4:
                $name = '申请未通过';
                break;
            default:
                $name = '未申请';
        }
        return $name;
    }



}