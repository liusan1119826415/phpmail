<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/3/25
 * Time: 下午1:48
 */

namespace app\common\models\member;


use app\common\models\BaseModel;

class InvitationCode  extends BaseModel
{
    public $table = 'yz_invitation_codes';

    public $guarded = [''];

    public function isValid()
    {
        return $this->used_count < $this->max_uses &&
            $this->expires_at > time();
    }
}
