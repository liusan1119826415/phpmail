<?php

namespace app\common\models\project;
use app\common\models\Address;
use app\common\models\BaseModel;
use app\common\models\Category;
use app\common\models\MemberCart;
use app\common\models\project\Floors;
use Illuminate\Database\Eloquent\SoftDeletes;

class InstallExpress extends BaseModel
{

   // use SoftDeletes;
    protected $table = 'yz_install_express';

    public function category()
    {
        return $this->belongsTo(Category::class,'category_id');
    }

    public static function quickUpdatedDispatch($id, $type,$status)
    {
        return self::where('id', $id)->update([$type => $status]);
    }

}