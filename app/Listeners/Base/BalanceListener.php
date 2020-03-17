<?php

namespace App\Listeners\Base;

use App\Events\Base\BalanceEvent;
use App\Models\Base\Simple\BalanceRecord;
use App\Models\Base\UserBalance;
use App\Services\Base\GlobalConfigService;
use App\Services\Base\UserCacheService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BalanceListener
{
    public function handle(BalanceEvent $event)
    {
        DB::transaction(function () use ($event){
            $record = new BalanceRecord();
            $date = GlobalConfigService::getDate();
            $record['date'] = $date;
            $record['user_id'] = $event->user_id;
            $record['type'] = $event->type;
            $record['method'] = $event->method;
            $record['money'] = $event->money;
            $record['chips'] = $event->chips;
            $record['param1'] = $event->param1;
            $record['param2'] = $event->param2;
            $record->save();

            $user_balance = UserBalance::find($event->user_id);

            if($event->money != 0)
                $user_balance->increment("money",$event->money);
            if($event->chips != 0)
                $user_balance->increment("chips",$event->chips);
            UserCacheService::setBalance($event->user_id,$user_balance->balance());
        });
    }
}
