<?php
/**
 * Created by PhpStorm.
 * Date: 9/12/23
 * Time: 2:42 PM
 */

namespace app\frontend\modules\member\models;


use app\common\models\BaseModel;

class MemberStateGrid extends BaseModel
{
    public $table = 'yz_member_state_grid';
    public $guarded = [''];
}