<?php

namespace App\Models\Base;

use App\Models\Base\Simple\ABTestPushConfig;
use App\Services\Base\MathService;
use App\Services\Base\RedisService;

class UserPush extends UserModel
{
    public function init()
    {
        $this['index'] = MathService::randomFromString(sha1("UserPush".$this['id']),1000);
        parent::init();
    }

    public function active()
    {
        $this->updateTags();
        parent::active();
    }

    /**
     *只更新target=2的那些个AB测试
     */
    public function updateTags()
    {
        $configs = RedisService::cacheObject("UserPush::updateTags",null,function (){
            return ABTestPushConfig::query()->where('target',2)->get()->toArray();
        });
        $modify = false;
        foreach ($configs as $config)
        {
           $name = $config['name'];
           if(isset($this[$name]))
           {
               continue;
           }
           if(MathService::randomFromString($name.$this['index'],$config['A'] + $config['B']) < $config['A'])
           {
                $this[$name] = 'A';
           }
           else
           {
               $this[$name] = 'B';
           }
           $modify = true;
        }
        if($modify)
        {
            try{
                $this->save();
            }
            catch (\Exception $exception)
            {

            }
        }
    }

    /**
     * 针对个体的推送：即将发送推送前，请求用户属于哪个分组，如果已经生成（一般意味着已经给他发送过一次）那么不处理，否则就存入tag，表示给这个用户发送过这个推送了
     * @param $name
     * @return mixed
     */
    public function updateTagForName($name)
    {
        if(isset($this[$name]))
        {
            return $this[$name];
        }
        $config = RedisService::cacheObject("UserPush::updateTagForName",$name,function () use ($name){
            return ABTestPushConfig::query()->where('name',$name)->first()->toArray();
        });
        if(MathService::randomFromString($name.$this['index'],$config['A'] + $config['B']) < $config['A'])
        {
            $this[$name] = 'A';
        }
        else
        {
            $this[$name] = 'B';
        }
        try{
            $this->save();
        }
        catch (\Exception $exception)
        {

        }
        return $this[$name];
    }

    public function setOnesignalId($onesignal_id)
    {
        $this['onesignal_id'] = $onesignal_id;
        $this->save();
    }
}
