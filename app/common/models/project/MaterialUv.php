<?php

namespace app\common\models\project;
use app\common\models\BaseModel;
class MaterialUv extends BaseModel
{
    protected $table = 'yz_material_uv';

    public $fillable = ["name","metalness","roughness","opacity"];

}