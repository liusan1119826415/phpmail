<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/3/25
 * Time: 下午1:48
 */

namespace app\common\models\member;


use app\common\models\BaseModel;

class SendMessageReport  extends BaseModel
{
    public $table = 'yz_send_message_report';

    public $guarded = [''];


    public static function createReport(string $title, string $content,int $sub_type, int $send_num,int $send_success,int $send_fail)
    {
        return self::create([
            'title' => $title,
            'content' => $content,
            'sub_type' => $sub_type,
            'send_num' => $send_num,
            'send_success' => $send_success,
            'send_fail' => $send_fail,
        ]);
    }

    public static function updateReport($id,$send_success,$send_fail){
        return self::where('id',$id)->update([
            'send_success' => $send_success,
            'send_fail' => $send_fail,
        ]);

    }
  



    
}
