<?php

namespace app\common\events\home;

use app\common\events\Event;

class LocationInfoEvent extends Event
{
    protected $adcode;

    public function __construct($adcode)
    {
        $this->adcode = $adcode;
    }

    /**
     * 区县ID
     * @return mixed
     */
    public function getAdcode()
    {
        return $this->adcode;
    }

}