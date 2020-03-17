<?php

namespace App\Services\Base;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redis;

class RedisService
{
    static protected $prefix = "__pf__";
    static protected $suffix = "__sf__";

    static protected $local_cache = [];
    static protected $center_cache = [];

    static public function center()
    {
        return Redis::connection("center");
    }

    static public function local()
    {
        return Redis::connection("cache");
    }

    /**
     * @param $name
     * @param $keys
     * @return string
     */
    static protected function makeKey($name,$keys)
    {
        if(is_array($keys))
        {
            $key = self::$prefix.$name.self::$suffix.implode(",",$keys);
        }
        else
        {
            $key = self::$prefix.$name.self::$suffix.$keys;
        }
        if(strlen($key) > 64)
            return sha1($key);
        return $key;
    }

    /**keys 为数组或者字符串
     * @param $name
     * @param string | array $keys
     * @param $callback
     * @param int $expire
     * @return mixed
     */
    static public function cacheObject($name,$keys,$callback,$expire = 300)
    {
        $key = self::makeKey($name,$keys);
        if(isset(self::$local_cache[$key]))
        {
            return self::$local_cache[$key];
        }
        $string = self::local()->get($key);
        if(is_null($string))
        {
            $data = $callback();
            self::local()->setex($key,$expire,serialize(["data" => $data]));
            self::$local_cache[$key] = $data;
            return $data;
        }
        else
        {
            $pack = unserialize($string);
            $data = $pack['data'];
            self::$local_cache[$key] = $data;
            return $data;
        }
    }

    static public function rootCacheObject($name,$keys,$callback,$expire = 300)
    {
        $key = self::makeKey($name,$keys);
        if(isset(self::$center_cache[$key]))
        {
            return self::$center_cache[$key];
        }
        $string = self::center()->get($key);
        if(is_null($string))
        {
            $data = $callback();
            self::center()->setex($key,$expire,serialize(["data" => $data]));
            self::$center_cache[$key] = $data;
            return $data;
        }
        else
        {
            $pack = unserialize($string);
            $data = $pack['data'];
            self::$center_cache[$key] = $data;
            return $data;
        }
    }

    /**
     * 当有效时间小于总时间的一半，就会自动续期
     * @param $name
     * @param $keys
     * @param $callback
     * @param int $expire
     * @return mixed
     */
    static public function rootCacheObjectAutoReNew($name,$keys,$callback,$expire = 57600)
    {
        $key = self::makeKey($name,$keys);
        $string = Redis::connection("root")->get($key);
        if(is_null($string))
        {
            $data = $callback();
            $expire_time = time() + $expire;
            self::center()->setex($key,$expire,serialize(["data" => $data,"expire_time" => $expire_time]));
            return $data;
        }
        else
        {
            $pack = unserialize($string);
            $expire_time = Arr::get($pack,'expire_time',0);
            $data = $pack['data'];
            if($expire_time < time() + $expire / 2)
            {
                $expire_time = time() + $expire;
                self::center()->setex($key,$expire,serialize(["data" => $data,"expire_time" => $expire_time]));
            }
            return $data;
        }
    }

    /**删除
     * @param $name
     * @param $keys
     */
    static public function deleteCacheObject($name,$keys)
    {
        $key = self::makeKey($name,$keys);
        unset(self::$local_cache[$key]);
        self::local()->del($key);
    }

    static public function rootDeleteCacheObject($name,$keys)
    {
        $key = self::makeKey($name,$keys);
        unset(self::$center_cache[$key]);
        self::center()->del($key);
    }
}
