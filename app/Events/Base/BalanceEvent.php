<?php

namespace App\Events\Base;

use App\Events\Event;

class BalanceEvent extends Event
{
    public $user_id;
    public $type;
    public $method;
    public $money;
    public $chips;
    public $param1;
    public $param2;

    public function __construct($user_id,$type,$method,$money,$chips,$param1 = 0,$param2 = 0)
    {
        $this->user_id = $user_id;
        $this->type = $type;
        $this->method = $method;
        $this->money = $money;
        $this->chips = $chips;
        $this->param1 = $param1;
        $this->param2 = $param2;
    }
}
