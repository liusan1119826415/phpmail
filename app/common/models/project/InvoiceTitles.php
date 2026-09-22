<?php

namespace app\common\models\project;
use app\common\models\Address;
use app\common\models\BaseModel;
use app\common\models\MemberCart;
use app\common\models\project\Floors;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceTitles extends BaseModel
{

   // use SoftDeletes;
    protected $table = 'yz_invoice_titles';

    public $timestamps = true;

    public $fillable = ["member_id","title_name","is_default"];

}