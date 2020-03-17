<?php

namespace App\Services\Base;

use App\Http\Controllers\Base\Controller;
use App\Models\Base\Simple\ABTestPushConfig;
use App\Models\Base\Simple\PlatformAppConfig;
use App\Models\Base\Simple\PushMessageConfig;
use App\Models\Base\Simple\PushRecord;
use App\Models\Base\UserLogin;
use App\Models\Base\UserPush;
use Berkayk\OneSignal\OneSignalClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PushService
{
    static $clients = [];
    const user_auth_key = "ODlhMWYxNjQtM2U1ZC00YTRmLWEyM2YtMDk3ZGVhNTcyODgz";

    static public function bundleArray()
    {
        return RedisService::cacheObject("one_signal_bundles",null,function (){
            return PlatformAppConfig::query()->where('key','onesignal_app_id')->pluck('bundle_id');
        },300);
    }

    static public function setTagForUser($user_id)
    {
        $user_push = UserPush::find($user_id);
        $user_login = UserLogin::find($user_id);
        //Controller::clientTag("user_id",$user_id);
        Controller::clientTag('index',$user_push['index']);
        //Controller::clientTag('version',$user_push['version']);
        //Controller::clientTag('country',$user_push['country']);
        //Controller::clientTag('active_date',$user_push['active_date']);
        //Controller::clientTag('register_time',strtotime($user_login['created_at']));
        //Controller::clientTag('login_time',time());
    }

    static public function setLanguageGroup($user_id,$language)
    {
        $bv = UserCacheService::bundleAndVersion($user_id);
        $language_group = RedisService::cacheObject("push_language_sets",[$language,$bv['bundle_id'],$bv['version']],
            function () use ($language,$bv){
            $content = PlatformConfigService::configForKey("language_group",$bv['bundle_id'],$bv['version']);
            $data = json_decode($content,true);
            if(isset($data[$language]))
            {
                return $data[$language];
            }
            else
            {
                return "en";
            }
        });
        Controller::clientTag("language_group",$language_group);
    }

    static public function clientWithBundleId($bundle,$key = null)
    {
        if(!isset(self::$clients[$bundle.$key]))
        {
            $client = new OneSignalClient(
                PlatformConfigService::configForKey("onesignal_app_id",$bundle,0),
                PlatformConfigService::configForKey("onesignal_app_key",$bundle,0),
                PushService::user_auth_key
            );
            $client->async();
            self::$clients[$bundle.$key] = $client;
            //$this->renderForAndroid($this->clientWithBundle($bundle));
        }
        if(isset(self::$clients[$bundle.$key]))
            return self::$clients[$bundle.$key];
        return null;
    }

    static public function pushToUser($user_id,$message,$title = null,$data = null,$schedule = null,$language_group = 'en')
    {
        $tag = [
            'field' => 'tag',
            'key' => 'user_id',
            'relation' => '=',
            'value' => $user_id,
        ];
        $user_login = UserLogin::find($user_id);
        $client = self::clientWithBundleId($user_login['bundle_id']);
        self::push($client,$title,$message,$language_group,[$tag],$data,$schedule,null,null);
    }

    static public function pushToTags($tags,$message,$title = null,$data = null,$send_begin_at = null,$language_group = 'en',$bundle_array = null)
    {
        if($bundle_array == null)
            $bundle_array = self::bundleArray();
        foreach ($bundle_array as $bundle) {
            $client = self::clientWithBundleId($bundle);
            self::push($client,$title,$message,$language_group,$tags,$data,$send_begin_at,null,null);
        }
    }

    static public function pushFeatureToTags($feature,$tags,$data = null,$send_begin_at = null,$message_render = null,$divide_number = 1,$use_abtest = false)
    {
        $bundle_array = self::bundleArray();
        foreach ($bundle_array as $bundle_id)
        {
            $languages = PushMessageConfig::query()->where('feature',$feature)
                ->whereIn('bundle_id',[$bundle_id,'custom'])
                ->where('status',1)
                ->get(DB::raw("DISTINCT language_group"));
            foreach ($languages as $item)
            {
                $language = $item['language_group'];
                $temp_tags = $tags;
                $temp_tags[] = [
                    "field" => "tag",
                    "key" => "language_group",
                    "relation" => "=",
                    "value" =>$language
                ];
                $sendFunction = function ($tags,$send_begin_at) use ($language,$feature,$bundle_id,$message_render,$data) {
                    $config = PushMessageConfig::getConfigByFAndB($feature,$bundle_id,['language_group' => $language]);
                    if($config == null)
                    {
                        LogService::alert("push_feature_null",$feature,$bundle_id,$language);
                        return;
                    }
                    $client = self::clientWithBundleId($bundle_id,$language);
                    $client->callback(function (GuzzleHttp\Psr7\Response $response) use ($bundle_id,$feature,$config){
                        $data = json_decode($response->getBody(),true);
                        if(isset($data["id"]) && strlen($data['id']) > 0)
                        {
                            $record = new PushRecord();
                            $record['date'] = GlobalConfigService::getDate();
                            $record['notification_id'] = $data['id'];
                            $record['feature'] = $feature;
                            $record['bundle_id'] = $bundle_id;
                            $record['config_id'] = $config['id'];
                            $record->save();
                        }
                    });
                    if($message_render && is_callable($message_render))
                    {
                        $message = $message_render($config['message']);
                    }
                    else
                    {
                        $message = $config['message'];
                    }
                    LogService::log("push",$config['title'],$message,$language,json_encode($tags),$send_begin_at);
                    /*self::push($client,$config['title'],$message,$language,$tags,$data,$send_begin_at,null,null)*/;
                };

                if($divide_number == 1 && !$use_abtest)
                {
                    $sendFunction($temp_tags,$send_begin_at);
                }
                else
                {
                    $begin_time_ts = $send_begin_at ? strtotime($send_begin_at) : time();
                    $a = MathService::getRandomCoprime(1000);
                    $b = MathService::getRandomCoprime(1000);

                    $pack = [];

                    $config = ABTestPushConfig::query()->where('name',$feature)->first();

                    for($i = 0;$i < 1000;$i ++)
                    {
                        $tag_index = [
                            'field' => 'tag',
                            'key' => 'index',
                            'relation' => '=',
                            'value' => $i
                        ];
                        if($use_abtest &&
                            MathService::randomFromString($feature.$i,$config['A'] + $config['B']) >= $config['A'])
                        {
                            continue;
                        }
                        $send_time_index = ($a * $i + $b) % 1000 % $divide_number;
                        echo $i.",".$send_time_index.",";
                        if(!isset($pack[$send_time_index]))
                        {
                            $pack[$send_time_index] = [];
                        }
                        if(count($pack[$send_time_index]) > 0)
                        {
                            $pack[$send_time_index][] = ["operator" => "OR"];
                        }

                        foreach ($temp_tags as $tag)
                        {
                            $pack[$send_time_index][] = $tag;
                            if(isset($tag['operator']))
                            {
                                LogService::alert("send_feature_or_error",$feature,json_encode($tags));
                            }
                        }

                        $pack[$send_time_index][] = $tag_index;
                        echo count($pack[$send_time_index])."\n";
                        if(count($pack[$send_time_index]) > 150)
                        {
                            $send_at = Carbon::createFromTimestamp($begin_time_ts + 60 * $send_time_index)->format('Y-m-d H:i:s');
                            $sendFunction($pack[$send_time_index],$send_at);
                            $pack[$send_time_index] = [];
                        }
                    }
                    foreach ($pack as $send_time_index => $ts)
                    {
                        if(count($ts) == 0)
                            continue;
                        $send_at = Carbon::createFromTimestamp($begin_time_ts + 60 * $send_time_index)->format('Y-m-d H:i:s');
                        $sendFunction($ts,$send_at);
                    }
                }
            }
        }
    }

    static public function appendOrganic(&$tags)
    {

    }

    static public function appendNotOrganic(&$tags)
    {

    }

    static protected function push(OneSignalClient $client,$title,$message,$language_group,$tags,$data,$schedule,$segment,$player_ids)
    {
        $params = [];
        $params['contents'] = [$language_group => $message];
        if ($data) {
            $params['data'] = $data;
        }
        if($schedule){
            $params['send_after'] = $schedule;
        }
        if($title){
            $params['headings'] = [$language_group => $title];
        }
        if($tags){
            $params['filters'] = $tags;
        }
        if($segment){
            $params['included_segments'] = [$segment];
        }
        if($player_ids){
            $params['include_player_ids'] = $player_ids;
        }
        $client->sendNotificationCustom($params);
    }

    /**
     * 给push_record获取报告，在created_at 的24小时后
     */
    public function reportNext()
    {
        $yesterday = Carbon::createFromTimestamp(time() - 86400)->format('Y-m-d H:i:s');
        $records = PushRecord::query()
            ->where('reported',0)
            ->where('created_at','<',$yesterday)
            ->limit(30)
            ->get();
        foreach ($records as $record)
        {
            $client = $this->clientWithBundle($record['bundle_id']);
            /** @var GuzzleHttp\Psr7\Response $response */
            $response = $client->getNotification($record['notification_id']);
            if($response->getStatusCode() == 200)
            {
                $data = json_decode($response->getBody(),true);
                $record['success'] = $data['successful'];
                $record['failed'] = $data['failed'];
                $record['click'] = $data['converted'];
                $record['convert_rate'] = $record['success'] > 0 ? round($record['click'] / $record['success'] * 100,6) : 0;
                $record['reported'] = 1;
                $record->save();
            }
        }
    }
}
