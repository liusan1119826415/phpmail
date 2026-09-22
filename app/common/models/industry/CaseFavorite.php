<?php

namespace app\common\models\industry;
use app\common\models\BaseModel;
use Yunshop\Supplier\common\models\IndustryCase;
class CaseFavorite extends BaseModel
{
    protected $table = 'yz_case_favorites';

    public $fillable = ["member_id","case_id"];


    public function industryCase()
    {
        return $this->belongsTo(IndustryCase::class, 'case_id');
    }
}