<?php


namespace App\Events\Base;;
use App\Events\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Base\UserCacheService;
use App\Services\Base\RedisService;
use App\Models\Base\Simple\NetworkWorth;
use App\Services\Base\GlobalConfigService;

class AdEvent extends Event
{
    const default_worth = 6;

    public $user_id;
    public $type;
    public $method;
    public $network_name;
    public $network_id;
    public $ip;
    public $worth;

    /**
     * @param $user_id
     * @param $network_id
     * @param $type
     * @param $network_name
     * @param $ip
     */
    public function __construct($user_id,$network_id,$type,$method,$network_name,$ip)
    {
        $this->user_id = $user_id;
        $country = UserCacheService::country($user_id);
        $this->type = $type;
        $this->method = $method;
        $this->network_name = $network_name;
        $this->network_id = $network_id;
        $this->ip = $ip;
        $this->worth = self::default_worth / 1000;
//        $this->worth = self::worthForNetworkId($network_id,$network_name,$country);
    }

    /**
     * @param Request $request
     * @param $type
     * @return mixed
     */
    static public function createFromRequest(Request $request,$type)
    {
        /**
         * 旧版本是param,新版本是ad_source
         */
        $param = $request->post('param',null);
        if($param == null)
        {
            $param = $request->post('ad_source',null);
        }

        $ip = isset($_SERVER["HTTP_X_FORWARDED_FOR"]) ? $_SERVER["HTTP_X_FORWARDED_FOR"] : $request->ip();
        $user = Auth::user();
        $user_id = $user['id'];
        $network_id = $request->post('network_id',null);
        $method = $request->post('method',null);
//        $ret = new AdEvent($user_id,$network_id,$type,$param,$ip);
//        return json_decode(json_encode($ret),true);
        return new AdEvent($user_id,$network_id,$type,$method,$param,$ip);
    }

    /**
     * @param $network_id
     * @param $network_name
     * @param $country
     * @return mixed
     */
    static public function worthForNetworkId($network_id,$network_name,$country)
    {
        return RedisService::cacheObject("network_worth".$network_name.$country,$network_id,function () use ($network_id,$network_name,$country){
            try{
                $record = NetworkWorth::query()->where('network_id',$network_id)
                    ->where('country',strtoupper($country))
                    ->orderBy('date','desc')
                    ->first();
                if($record == null)
                {
                    $record = NetworkWorth::query()->where('network_id',$network_id)
                        ->where('country','US')
                        ->orderBy('date','desc')
                        ->first();
                }
                if($record == null)
                {
                    $record = NetworkWorth::query()->where('network_id',$network_id)
                        ->orderBy('date','desc')
                        ->first();
                }

                if($record == null)
                {
                    $date = GlobalConfigService::getDate(time() - 86400 * 2);
                    $results = DB::select("select sum(worth) / count(*) as a from
(select max(id) as id from network_worth WHERE network_name = '$network_name' and date > $date GROUP BY line_id)p 
left join network_worth on network_worth.id = p.id ");
                    if(count($results) == 0)
                        return self::default_worth / 1000;
                    $average = $results[0]->a;
                    if($average === null)
                        $average = self::default_worth;
                    return $average / 1000;
                }
                if($record == null)
                    return self::default_worth / 1000;
                return $record['worth'] / 1000;
            }
            catch (\Exception $e)
            {
                Log::info($e->getTraceAsString());
            }
            return self::default_worth / 1000;
        },60);
    }


}
