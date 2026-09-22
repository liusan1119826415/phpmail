<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/3/25
 * Time: 下午1:48
 */

namespace app\common\models\member;


use app\common\models\BaseModel;
use app\common\models\Member;

class UsedInvitation  extends BaseModel
{
    public $table = 'yz_used_invitations';

    public $guarded = [''];



    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id','uid');
    }

    public function invitationCode()
    {
        return $this->belongsTo(InvitationCode::class, 'code_id');
    }
}
