<?php

namespace app\common\events\member;

use app\common\events\Event;

class SkipLoginRequestEvent extends Event
{

    protected $extra_data;
    protected $member_id;

    public function __construct($member_id, $extra_data)
    {
        $this->extra_data = $extra_data;
        $this->member_id = $member_id;
    }

    public function getExtraData()
    {
        return $this->extra_data;
    }
    public function getMemberID()
    {
        return $this->member_id;
    }
}