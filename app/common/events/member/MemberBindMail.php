<?php

namespace app\common\events\member;


use app\common\events\Event;

class MemberBindMail extends Event
{
    private $data = '';

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function getMemberModel()
    {
        return $this->data;
    }
}