<?php

namespace App\Services\Base;

use App\Models\Base\Simple\Device;
use App\Models\Base\UserLogin;
use App\Models\Base\UserBalance;
use App\User;

class UserCacheService
{
    //查询均未确定，可能需要修改--------

    //bundle和version的缓存
    static protected $bundle_version_cache = [];
    //用户余额的缓存
    static protected $account_balances_cache = [];

    //获取bundle和Version
    /**
     * @param $user_id
     * @return array|mixed
     */
    static function bundleAndVersion($user_id)
    {
        if (isset(self::$bundle_version_cache[$user_id])){
            return self::$bundle_version_cache[$user_id];
        }else{
            $key = "uc_bundle_and_version".$user_id;
            $data = unserialize(RedisService::center()->get($key));
            if (!empty($data)){
                $bundle_id = $data[0];
                $version = $data[1];
                $ret = ["bundle_id" => $bundle_id,"version" => $version];
                self::$bundle_version_cache[$user_id] = $ret;
                return $ret;
            }else{
                $res = UserLogin::find($user_id);
                if (!empty($res)){
                    $bundle_id = $res['bundle_id'];
                    $version = $res['version'];
                    self::setBundleAndVersion($user_id,$bundle_id,$version);
                    $ret = ["bundle_id" => $bundle_id,"version" => $version];
                    return $ret;
                }else{
                    return ["bundle_id" => "","version" => 0];
                }
            }
        }
    }

    //储存bundle和Version
    /**
     * @param $user_id
     * @param $bundle_id
     * @param $version
     */
    static function setBundleAndVersion($user_id,$bundle_id,$version)
    {
        self::$bundle_version_cache[$user_id] = ["bundle_id" => $bundle_id,"version" => $version];
        $key = "uc_bundle_and_version".$user_id;
        $expire_time = 86400;
        $data = serialize([$bundle_id,$version]);
        RedisService::center()->setex($key,$expire_time,$data);
    }

    //储存账户余额
    /**
     * @param $user_id
     * @param $balance
     */
    static public function setBalance($user_id,$balance)
    {
        $key = "uc_account_balances".$user_id;
        $expire_time = 86400;
        $data = serialize($balance);
        RedisService::center()->setex($key,$expire_time,$data);
        self::$account_balances_cache[$user_id] = $balance;
    }

    //获取账户余额
    /**
     * @param $user_id
     * @return array|mixed
     */
    static function balance($user_id){
        if (isset(self::$account_balances_cache[$user_id])){
            return self::$account_balances_cache[$user_id];
        }else{
            $key = "uc_account_balances".$user_id;
            $ret = unserialize(RedisService::center()->get($key));
            if (!empty($ret)){
                return $ret;
            }else{
                $res = UserBalance::find($user_id);
                if(empty($res))
                    return ["money" => 0,"chips" => 0];
                self::setBalance($user_id,$res->balance());
                return $res->balance();
            }
        }
    }

    //储存用户国家
    /**
     * @param $user_id
     * @param $country
     */
    static public function setCountry($user_id,$country)
    {
        $key = "uc_country".$user_id;
        $expire_time = 86400;
        $data = $country;
        RedisService::center()->setex($key,$expire_time,$data);
    }

    //获取用户国家
    /**
     * @param $user_id
     * @return mixed|string
     */
    static public function country($user_id)
    {
        $key = "uc_country".$user_id;
        $country = RedisService::center()->get($key);
        if($country)
        {
            return $country;
        }
        else
        {
            $res = UserLogin::find($user_id);
            if(empty($res))
                return "US";
            self::setCountry($user_id,$res['country']);
            return $res['country'];
        }
    }
}
