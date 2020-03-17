<?php

namespace App\Services\Base;

use App\Models\Base\Simple\PlatformAppConfig;
use App\Models\Base\Simple\PlatformFeatureConfig;
use Illuminate\Support\Facades\DB;
use phpDocumentor\Reflection\Types\Null_;

class PlatformConfigService
{
    static protected $cashConfigCache = [];

    /**根据key，bundle_id, version 获得配置
     * @param $key
     * @param $bundle_id
     * @param $version
     * @param null $default
     * @return mixed
     */
    static public function configForKey($key,$bundle_id,$version,$default = null)
    {
        if(isset(self::$cashConfigCache[$key.$bundle_id.$version]))
        {
            return self::$cashConfigCache[$key.$bundle_id.$version];
        }
        /**  */
        $ret = RedisService::cacheObject("platform_config_key",[$key,$bundle_id,$version],
            function () use ($key,$bundle_id,$version,$default){
            $config1 = PlatformAppConfig::query()->where('key',$key)->where('bundle_id',$bundle_id)->where('version','<=',$version)
                ->orderBy('version','desc')->first();
            $config2 = PlatformFeatureConfig::query()->where('key',$key)->where('bundle_id',$bundle_id)->where('version','<=',$version)
                ->orderBy('version','desc')->first();
            if(!is_null($config1) && !is_null($config2))
            {
                /**
                 * 这里需要发出警报,同一配置不应该出现在两个配置表里
                 */
                LogService::alert("platform config alert","configForKey",$key,$bundle_id,$version);
                return $config2['value'];
            }
            else if(!is_null($config1))
            {
                return $config1['value'];
            }
            else if(!is_null($config2))
            {
                return $config2['value'];
            }
            return $default;
        });
        self::$cashConfigCache[$key.$bundle_id.$version] = $ret;
        return $ret;
    }

    /**根据用户ID获得配置
     * @param $key
     * @param $user_id
     * @param null $default
     * @return mixed
     */
    static public function configForKeyByUserId($key,$user_id,$default = null)
    {
        $bv = UserCacheService::bundleAndVersion($user_id);
        return self::configForKey($key,$bv['bundle_id'],$bv['version'],$default);
    }

    /**根据功能获得配置组
     * @param $feature
     * @param $bundle_id
     * @param $version
     * @return mixed
     */
    static public function configsForFeature($feature,$bundle_id,$version)
    {
        $data = RedisService::cacheObject("platform_config_for_feature",[$feature,$bundle_id,$version],
            function () use ($feature,$bundle_id,$version){
                $tables = ["platform_app_configs","platform_feature_configs"];
                $data = [];
                foreach ($tables as $table)
                {
                    $results = DB::select("select $table.* from
(select `key`,max(version) as version from $table WHERE bundle_id = '$bundle_id'
and version <= $version and `feature` = '$feature'
GROUP BY `key`)p
left join $table on p.key = $table.key and p.version = $table.version
and bundle_id = '$bundle_id'");
                    foreach ($results as $result)
                    {
                        if(isset($data[$result->key]))
                        {
                            LogService::alert("platform config alert","configsForFeature",$feature,$bundle_id,$version);
                        }
                        $data[$result->key] = $result->value;
                    }
                }
                return $data;
        });
        /** 这里加一个内存缓存，这个方法可以作为预加载配置用 */
        foreach ($data as $key => $value)
        {
            self::$cashConfigCache[$key.$bundle_id.$version] = $value;
        }
        return $data;
    }

    /**
     * 多个key获取配置
     * @param $keys
     * @param $bundle_id
     * @param $version
     * @param array $defaults
     * @return mixed
     */
    static public function configForKeys($keys,$bundle_id,$version,$defaults = [])
    {
        //传入一个keys的数组，如果某个key不存在，就不返回这个key的值
        //例如传入 ['a','b','c']，如果b的配置不存在，就返回["a" => 1,"c" => "10"],没有b
        $configs1 = array();
        $configs2 = array();
        $keys_str = "'".join("','",$keys)."'";
        return RedisService::cacheObject("config_for_keys",[$bundle_id,$version,$keys_str],
            function () use ($keys,$bundle_id,$version,$keys_str,$defaults){
                $config1 = DB::select("select platform_feature_configs.* from (select `key`,MAX(version) as version from platform_feature_configs where `key` in (".$keys_str.") and version <= $version and bundle_id = '".$bundle_id."' GROUP BY `key`)p LEFT JOIN platform_feature_configs on p.key = platform_feature_configs.key and p.version = platform_feature_configs.version");

                $config2 = DB::select("select platform_app_configs.* from (select `key`,MAX(version) as version from platform_app_configs where `key` in (".$keys_str.") and version <= $version and bundle_id = '".$bundle_id."' GROUP BY `key`)p LEFT JOIN platform_app_configs on p.key = platform_app_configs.key and p.version = platform_app_configs.version");
                $count_configs1 = 0;
                $count_configs2 = 0;
                $configs = [];
                $configs1 = [];
                $configs2 = [];
                if (!empty($config1))
                {
                    foreach ($config1 as $value1)
                    {
                        $v1 = (array)$value1;
                        $configs1[$v1['key']] = $v1['value'];
                        $configs[$v1['key']] = $v1['value'];
                    }
                    $count_configs1 = count($configs1);
                }
                if (!empty($config2))
                {
                    foreach ($config2 as $value2)
                    {
                        $v2 = (array)$value2;
                        $configs2[$v2['key']] = $v2['value'];
                        $configs[$v2['key']] = $v2['value'];
                    }
                    $count_configs2 = count($configs2);
                }
                //判断两组数据中是否有key,bundle_id都相同，且version都符合条件的数据
                $count = count($configs);
                if ($count < $count_configs1+$count_configs2)
                {
                    /**
                     * 这里需要发出警报,同一配置不应该出现在两个配置表里
                     */
                    LogService::alert("platform config alert","configForKeys",$keys,$bundle_id,$version);
                }
                foreach ($keys as $key)
                {
                    if(isset($configs[$key]))
                    {
                        self::$cashConfigCache[$key.$bundle_id.$version] = $configs[$key];
                    }
                    else if(isset($defaults[$key]))
                    {
                        self::$cashConfigCache[$key.$bundle_id.$version] = $defaults[$key];
                    }
                }
                return $configs;
            });
    }
}
