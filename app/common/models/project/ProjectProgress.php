<?php

namespace app\common\models\project;

use app\common\models\BaseModel;

class ProjectProgress extends BaseModel
{

    protected $table = 'yz_project_progress_data';


    public static function getCompanyType($type)
    {
       return self::select("id","name")->where('type',$type)->get()->toArray();
    }


}