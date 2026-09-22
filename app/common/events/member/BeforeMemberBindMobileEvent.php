<?php

namespace app\common\events\member;

use app\common\events\Event;

class BeforeMemberBindMobileEvent extends Event
{
    private $member;

    public function __construct($member)
    {
        $this->member = $member;
    }

    public function getMemberModel()
    {
        return $this->member;
    }
}