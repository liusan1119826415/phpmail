<?php


namespace app\common\models\kefu;
use Illuminate\Database\Eloquent\Model;
class ServiceUser extends Model
{

    protected $connection = 'kefu';
    protected $table = 'ym_im_service';

    public $timestamps = false;

    public function belongsToGroup()
    {
        return $this->belongsTo(ServiceGroup::class,'group_id','id');
    }



    //获取分配的客服supplier_id 为0 是获取官方客服
    public static function getDistributeService($supplier_id,$goods_id=0)
    {
        $supplier_id = 0;
//        $data = self::whereHas('belongsToGroup',function ($query) use($supplier_id){
//            $query->where('relation',$supplier_id);
//        })->first();

       /* $im_id = $data?$data->im_id:0;
        $service_base_url = config("app.KEFU_URL");
        $url = $service_base_url."?im_id={$im_id}";
        if($goods_id){
            $url .="&goods_id={$goods_id}";
        }*/
        $service_base_url = config("app.KEFU_URL");
        if($goods_id){
            $service_base_url .="?goods_id={$goods_id}";
        }

        return $service_base_url;

    }




}