<?php

namespace app\common\models\project;
use app\common\models\Address;
use app\common\models\BaseModel;
use app\common\models\Member;
use app\common\models\MemberCart;
use app\common\models\project\Floors;
use Illuminate\Database\Eloquent\SoftDeletes;

class RealNameVerification extends BaseModel
{

   // use SoftDeletes;
    protected $table = 'yz_real_name_verifications';



    public function member()
    {
        return $this->belongsTo(Member::class,'member_id','uid');
    }

    public static function search($search)
    {
        $model = self::query();
        if (!empty($search['name'])) {
            if (is_numeric($search['name'])) {

                $model->whereHas('member',  function ($query) use($search){
                    $query->where('mobile',$search['name']);
                });
            } else {
                $model->whereHas('member',  function ($query) use($search){
                    $query->where('nickname', 'like', '%'.$search['name']."%");
                });
            }
        }

        if (!empty($search['real_name'])) {
            $model->where('real_name', 'like', '%'.$search['real_name']."%");
        }
        if (isset($search['status']) && ($search['status'] !== '' || $search['status'] === 0)){
            $model->where('status', $search['status']);
        }


        return $model;
    }
}