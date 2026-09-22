<?php

namespace app\common\models\project;
use app\common\models\BaseModel;
use app\common\models\MemberCart;
class Notification extends BaseModel
{
    protected $table = 'yz_notifications';

    protected $fillable = ["member_id","title","content","notice_type","related_id","sub_type","template_key"];
    // JSON字段自动转换
    protected $json = ['extra_data'];

}