<?php


namespace app\common\models\kefu;
use Illuminate\Database\Eloquent\Model;
class ImUser extends Model
{

    protected $connection = 'kefu';
    protected $table = 'ym_im_user';

    public $timestamps = false;

    

    //根据user_id 获取用户信息
    public static function getUserInfo($user_id)
    {
        return self::where('user_id',$user_id)->first();
    }




}