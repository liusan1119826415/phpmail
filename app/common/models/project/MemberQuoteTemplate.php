<?php

namespace app\common\models\project;
use app\common\models\BaseModel;
use app\common\models\MemberCart;
class MemberQuoteTemplate extends BaseModel
{
    protected $table = 'yz_member_quote_template';

    public $fillable = ["member_id","field_data"];

//    protected $casts = [
//        'field_data' => 'json',
//    ];
//
//    protected $attributes = [
//        'field_data' => '[]'
//    ];
}