<?php

namespace App\Events\Base;

use App\Events\Event;

class ItemEvent extends Event
{
    public $user_id;
    public $method;
    public $param1;
    public $param2;
    public $items;

    static public function post($user_id,$type,$method,$amount,$param1 = 0,$param2 = 0)
    {
        $event = new ItemEvent();
        $event->user_id = $user_id;
        $event->items = [$type => $amount];
        $event->method = $method;
        $event->param1 = $param1;
        $event->param2 = $param2;
        event($event);
    }

    static public function batchPost($user_id,$items,$method,$param1 = 0,$param2 = 0)
    {
        $event = new ItemEvent();
        $event->user_id = $user_id;
        $event->items = $items;
        $event->method = $method;
        $event->param1 = $param1;
        $event->param2 = $param2;
        event($event);
    }
}
