<?php

namespace app\common\models\project;
use app\common\models\BaseModel;


class CustomerContact extends BaseModel
{


    protected $table = 'yz_customer_contact';



    public $guarded=[];

    public function member()
    {
        return $this->hasOne(\app\common\models\Member::class, 'uid', 'member_id');

    }

}