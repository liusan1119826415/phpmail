<?php

namespace app\common\models\project;
use app\common\models\BaseModel;

class PurchasingModes extends BaseModel
{

    protected $table = 'yz_purchasing_modes';


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function hasManyChildren()
    {
        return $this->hasMany(self::class, "parent_id");
    }








}