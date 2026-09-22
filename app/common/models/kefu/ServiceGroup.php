<?php


namespace app\common\models\kefu;
use Illuminate\Database\Eloquent\Model;
class ServiceGroup extends Model
{

    protected $connection = 'kefu';
    protected $table = 'ym_im_service_group';

    public $timestamps = false;


    public function serviceUser()
    {
        return $this->hasMany(ServiceUser::class,'group_id','id');
    }



}