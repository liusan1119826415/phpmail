<?php

namespace app\common\models\project;
use app\common\models\BaseModel;
use app\common\models\goods\PptTemplate;
class ProjectPdf extends BaseModel
{
    protected $table = 'yz_member_project_pdf';

    public $fillable = ["member_id","project_id","project_name","ppt_url",'total_slides','template_id'];


    public function template()
    {
        return $this->belongsTo(PptTemplate::class,'template_id');
    }

}