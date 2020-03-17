<?php

namespace App\Services\Base;

use Illuminate\Support\Arr;

class ConfigService
{
    protected $config_keys = [];
    protected $abtest_names = [];

    protected $abtest_tags = [];
    protected $config_values = [];

    protected $country;
    protected $bundle_id;
    protected $version;
    protected $user_id;

    protected $open_handlers = [];
    protected $config_handlers = [];

    /**
     * @param $user_id
     * @param $bundle_id
     * @param $version
     * @param $country
     * @return mixed
     */
    public function config($user_id,$bundle_id,$version,$country)
    {
        $this->country = $country;
        $this->bundle_id = $bundle_id;
        $this->version = $version;
        $this->user_id = $user_id;

        $this->addConfigs();

        return $this->output();
    }

    public function addConfigs()
    {
        /**
         *
        $this->appendConfig("cash",true,function (){
            return CashService::getCashConfig($this->country,$this->bundle_id,$this->valueForKey("cash_config_version"));
        },["cash_config_version" => 1]);

        // 告诉客户端，到了这些时间点，到服务端请求对应的接口
        $this->appendConfig("refresh",true,[$this,"refreshConfig"]);

        //
        $this->appendKeyValue("year",2019);
        $this->appendConfig("year",true,[$this,"year"],[],["t1","t2"]);
         */
    }

    protected function output()
    {
        /** 记下所有配置和对应的值，作为缓存的键 */
        $cache_keys = [$this->bundle_id,$this->version,$this->country];
        /** 准备配置 */
        $key_values = PlatformConfigService::configForKeys(array_keys($this->config_keys),$this->bundle_id,$this->version);
        foreach ($this->config_keys as $key => $default)
        {
            if(isset($key_values[$key]))
            {
                $this->config_values[$key] = $key_values[$key];
            }
            else
            {
                $this->config_values[$key] = $default;
            }
            $cache_keys[] = $key.$this->config_values[$key];
        }
        foreach ($this->config_values as $key => $value)
        {
            $cache_keys[] = $key.$value;
        }
        /** 准备AB分组 */
        $tags = ABTestService::tagsForUserId($this->user_id);
        foreach ($this->abtest_names as $name)
        {
            $this->abtest_tags[$name] = Arr::get($tags,$name,'X');
            $cache_keys[] = $name.$this->abtest_tags[$name];
        }

        /**
         * 获取配置
         */
        $configs = RedisService::cacheObject("configs",$cache_keys,function (){
            $data = [];
            foreach ($this->open_handlers as $name => $open_handler)
            {
                $is_open = is_callable($open_handler) ? $open_handler() : $open_handler;
                if($is_open)
                {
                    $config_handler = $this->config_handlers[$name];
                    $data[$name] = $config_handler();
                }
            }
            return $data;
        });
        return $configs;
    }

    /**
     * @param $name:  配置内容的名字
     * @param $is_open_handler:  配置是否下发的handler，一定是下发的，那么填 true
     * @param $config_handler :  获得配置的handler
     * @param array $config_keys:  需要预备的多包配置的key,格式为 ["key" => "default"],key没有配置时，使用 default
     * @param array $abtest_names:  需要预备的AB测试的分组
     */
    public function appendConfig($name,$is_open_handler,$config_handler,$config_keys = [],$abtest_names = [])
    {
        $this->config_keys = array_merge($this->config_keys,$config_keys);
        $this->abtest_names = array_merge($this->abtest_names,$abtest_names);
        $this->open_handlers[$name] = $is_open_handler;
        $this->config_handlers[$name] = $config_handler;
    }

    /**设置自定义配置
     * @param $key
     * @param $value
     */
    protected function appendKeyValue($key,$value)
    {
        $this->config_values[$key] = $value;
    }

    /**获取准备好的配置
     * @param $key
     * @return int|mixed
     */
    protected function valueForKey($key)
    {
        if(isset($this->config_values[$key])) {
            return $this->config_values[$key];
        }
        LogService::error("config_key not find",$key);
        return 0;
    }

    /**AB测试的值
     * @param $name
     * @return mixed|string
     */
    protected function tagForName($name)
    {
        if(isset($this->abtest_tags[$name]))
        {
            return $this->abtest_tags[$name];
        }
        LogService::error("config tag not find",$name);
        return 'A';
    }

    /**客户端刷新数据的时间
     * @return array
     */
    protected function refreshConfig()
    {
        $data = [];
        /** 每日重新登录 */
        $data["login"] = [
            GlobalConfigService::getDayEnd(),
            GlobalConfigService::getDayEnd(time() - 86400),
            GlobalConfigService::getDayEnd(time() + 86400)
            ];
        return $data;
    }
}
