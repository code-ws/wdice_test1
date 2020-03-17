<?php

namespace App\Services\Base;

use App\Models\Base\Simple\GlobalConfig;
use App\Services\Base\RedisService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use libphonenumber\PhoneNumberUtil;


class GlobalConfigService
{
    static public function configForKey($key)
    {
        return RedisService::cacheObject("global_configs",$key,function () use ($key){
            $m = GlobalConfig::query()->where('key',$key)->first();
            return $m['value'];
        },3600);
    }

    static public function getDate($timestamp = null)
    {
        if(is_null($timestamp))
        {
            $timestamp = time();
        }
        $begin_ts = self::configForKey("date_begin");
        /** 这里与之前的刮卡服务器代码逻辑不一样，这里是➕号 */
        return Carbon::createFromTimestamp($timestamp + $begin_ts)->format('Ymd');
    }

    /**获取一天结束时的时间戳
     * @param null $timestamp
     * @return int|mixed|null|string
     */
    static public function getDayEnd($timestamp = null)
    {
        if($timestamp == null)
        {
            $timestamp = time();
        }
        $begin_ts = self::configForKey("date_begin");
        $offset_ts = $begin_ts + $timestamp;
        return $offset_ts - $offset_ts % 86400 + 86400 - $begin_ts;
    }

    /**
     * 根据国家获取提现分档
     * @param $country
     * @return int
     */
    static public function getCountryLevel($country)
    {
        if ($country == null || $country == ""){
            return 4;
        }else{
            $result = GlobalConfig::query()->where('key','like','country_t%')->get()->toArray();
            if (!empty($result)){
                foreach ($result as $key=>$value){
                    $res = strpos($value['value'],$country);
                    if ($res !== false){
                        $level = explode('country_t',$value['key']);
                        return $level[1];
                    }
                }
            }
            return 4;
        }
    }

    /**
     * 根据国家获取区号
     * @param $country
     * @return mixed
     */
    static public function getCountryCode($country)
    {
        $country = strtoupper($country);
        return RedisService::cacheObject("phone_code_",$country,function () use ($country){
            if($country == "XX" || $country == "YY")
            {
                return 86;
            }
            $metadata = PhoneNumberUtil::getInstance()->getMetadataForRegion($country);
            if($metadata == null)
            {
                return 1;
            }
            return $metadata->getCountryCode();
        });
    }
}
