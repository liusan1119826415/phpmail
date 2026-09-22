<?php


namespace app\common\models\kefu;
use Illuminate\Database\Eloquent\Model;
class ImMessage extends Model
{

    protected $connection = 'kefu';
    protected $table = 'ym_im_message';

    public $timestamps = false;

    

    //根据user_id 获取用户未读消息
    public static function getUnReadMessageCount($user_id)
    {
        return self::where('user_id',$user_id)->where('read_by_user',0)->where('user_type',1)->count();
    }




}