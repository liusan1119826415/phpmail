<?php


namespace app\frontend\modules\project\models;

use app\common\models\project\MemberQuoteTemplate as BaseTemplate;
class MemberQuoteTemplate extends BaseTemplate
{


    public static function saveTemplate($data)
    {
       $template = self::where('member_id',$data['member_id'])->first();
       if($template){
           $template->field_data = $data['field_data'];
           return $template->save();
       }
       return self::create($data);
    }
}