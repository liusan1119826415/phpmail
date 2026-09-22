<?php

namespace app\common\models\project;
use app\common\models\Address;
use app\common\models\BaseModel;
use app\common\models\MemberCart;
use app\common\models\Order;
use app\common\models\project\Floors;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderInvoiceV2 extends BaseModel
{

   // use SoftDeletes;
    protected $table = 'yz_order_invoice_v2';

    public $timestamps = true;

    public function order()
    {
        return $this->belongsTo(Order::class,'order_id','id');
    }

    public function company()
    {
        return $this->belongsTo(CompanyAuths::class,'company_id','id');
    }

    public function title()
    {
        return $this->belongsTo(InvoiceTitles::class,'invoice_title_id','id');
    }

    public function uploads()
    {
        return $this->hasMany(OrderInvoiceUploads::class, 'order_invoice_id', 'id');
    }



}