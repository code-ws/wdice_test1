<?php

namespace App\Listeners\Base;

use App\Events\Base\ItemEvent;
use App\Models\Base\Simple\ItemRecord;
use App\Services\Base\GlobalConfigService;

class ItemListener
{
    protected $type_not_item = [1,2];

    public function handle(ItemEvent $event)
    {
        foreach ($event->items as $type => $amount)
        {
            /** 美金筹码等，不走这里 */
            if(in_array($type,$this->type_not_item))
                continue;
            $record = new ItemRecord();
            $record['user_id'] = $event->user_id;
            $record['date'] = GlobalConfigService::getDate();
            $record['type'] = $type;
            $record['method'] = $event->method;
            $record['amount'] = $amount;
            $record['param1'] = $event->param1;
            $record['param2'] = $event->param2;

            $call = [$this,"collect".$type];

            if(is_callable($call))
            {
                $call($event->user_id,$type,$event->method,$amount,$event->param1,$event->param2);
            }
            $record->save();
        }
    }
}
