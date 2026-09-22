<?php

namespace app\common\models\project;
use app\common\models\BaseModel;
class IssueOptions extends BaseModel
{
    protected $table = 'yz_issue_options';

    
    // 定义子级关系
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    


}