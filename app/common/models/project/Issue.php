<?php

namespace app\common\models\project;
use app\common\models\BaseModel;
class Issue extends BaseModel
{
    protected $table = 'yz_issue';

    public $casts = [
        'thumb_url' => 'json',
        'issue_parent'=>'json'
    ];


     protected $fillable = [
        'member_id',
        'content',
        'issue_id',
        'issue_parent',
        'thumb_url',
        'file_url',
    ];


    public function Member()
    {
        return $this->hasOne(\app\common\models\Member::class, 'uid', 'member_id');
    }

    public function Issue()
    {
        return $this->belongsTo(\app\common\models\project\IssueOptions::class, 'issue_id', 'id');
    }





    


}