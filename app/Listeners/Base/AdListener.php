<?php
namespace App\Listeners\Base;


use App\Models\Base\UserWorth;
use App\Events\Base\AdEvent;
use App\Models\Base\Simple\NetworkRecords;
use App\Services\Base\GlobalConfigService;
use Illuminate\Support\Facades\Log;
class AdListener
{
    public function handle(AdEvent $event)
    {
        $record = new NetworkRecords();
        $record['user_id'] = $event->user_id;
        $record['type'] = $event->type;
        $record['worth'] = $event->worth;
        $record['method'] = $event->method;
        $record['network_id'] = $event->network_id;
        $record['network_name'] = $event->network_name;
        $record['ip'] = $event->ip;
        $date = GlobalConfigService::getDate();
        $record['date'] = $date;
        $record->save();
        try
        {
            UserWorth::updateWorth($event->user_id,$event->worth);
        }
        catch (\Exception $e)
        {
            Log::info($e->getMessage());
            Log::info($e->getTraceAsString());
        }

    }
}
