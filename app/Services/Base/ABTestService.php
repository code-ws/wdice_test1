<?php

namespace App\Services\Base;



use App\Models\Base\Simple\ABTestConfig;
use App\Models\Base\Simple\ABTestUdid;
use App\Models\Base\Simple\ABTestUserId;
use Illuminate\Support\Facades\Log;

class ABTestService
{
    const Type_Udid = 1;
    const Type_User_Id = 2;
    const Type_Push = 3;

    /**打开或者关闭，数据保存到数据库的功能
     * 修改abtest数据表结构的时候，需要关闭
     * @param $type
     * @param $is_on
     */
    static public function setAllocStatus($type,$is_on)
    {
        $key = "abtest_alloc_".$type;
        if($is_on)
        {
            RedisService::center()->del($key);
        }
        else
        {
            RedisService::center()->setex($key,3600,1);
        }
    }

    static public function allowAlloc($type)
    {
        $key = "abtest_alloc_".$type;
        if(RedisService::center()->exists($key))
            return false;
        return true;
    }


    /**缓存单个config
     * @param $name
     * @return mixed
     */
    static public function configForName($name)
    {
        return RedisService::cacheObject("abtest_for_name",$name,function () use ($name){
            $record = ABTestConfig::query()->where('name',$name)->first()->toArray();
            /** 提前计算一下sum_weight */
            $record['sum_weight'] = $record['A'] + $record['B'] + $record['C'] + $record['D'] + $record['E'] + $record['F'];
            return $record;
        });
    }


    /**根据bundle_id和version缓存ab测试名称
     * @param $table_choose
     * @param $bundle_id
     * @param $version
     * @return mixed
     */
    static public function testNames($table_choose,$bundle_id,$version)
    {
        return RedisService::cacheObject("abtest_names",$bundle_id,function () use ($table_choose,$bundle_id,$version){
            $names = ABTestConfig::query()->where('bundle_id',$bundle_id)
                ->where('table',$table_choose)
                ->where('max_version','>=',$version)
                ->where('min_version','<=',$version)
                ->pluck('name');
            return $names;
        });
    }

    /**根据seed和abtest_config随机分组，seed为user_id或者udid
     * @param $config
     * @param $seed
     * @return mixed|string
     */
    static protected function randomFromConfig($config,$seed)
    {
        $name = $config['name'];
        $hash = sha1($name.$seed);
        $m = MathService::randomFromString($hash,$config['sum_weight']);
        $groups = ['A','B','C','D','E','F'];
        foreach ($groups as $g)
        {
            if($m < $config[$g])
                return $g;
            $m -= $config[$g];
        }
        return "X";
    }

    /**根据user_id 生成分组
     * @param $user_id
     * @param $bundle_id
     * @param $version
     * @return array
     */
    static public function allocUserIdTags($user_id,$bundle_id,$version)
    {
        $record = ABTestUserId::query()->where('user_id',$user_id)->first();
        $is_user_new = false;
        if(is_null($record))
        {
            $is_user_new = true;
            $record = new ABTestUserId();
            $record['user_id'] = $user_id;
        }
        $table_choose = 'user_id';
        $names = self::testNames($table_choose,$bundle_id,$version);
        $data = [];
        $divide_group = false;
        foreach ($names as $name)
        {
            $config = self::configForName($name);

            /** 测试已经有结果了 */
            if($config['result'])
            {
                $data[$name] = $config['result'];
                continue;
            }
            /** 已经分配 */
            if(isset($record[$name]))
            {
                $data[$name] = $record[$name];
            }
            else
            {
                $end_time = strtotime($config['end_at']);
                if($config['target'] == 1 && $is_user_new == false)
                {
                    $record[$name] = 'X';
                }elseif (time() > $end_time){
                    $record[$name] = 'X';
                }
                else
                {
                    $record[$name] = self::randomFromConfig($config,$user_id);
                }
                $divide_group = true;
                $data[$name] = $record[$name];
            }
        }
        /** 有新加分组信息 而且 允许保存到数据库（尚未处于修改数据表状态） */
        if($divide_group && self::allowAlloc(self::Type_User_Id))
        {
            try
            {
                $record->save();
            }
            catch (\Exception $exception)
            {

            }

        }
        return $data;
    }

    /**根据user_id获取分组
     * @param $user_id
     * @return mixed
     */
    static public function tagsForUserId($user_id)
    {
        return RedisService::cacheObject("tagForUserId",$user_id,function () use ($user_id){
            $bv = UserCacheService::bundleAndVersion($user_id);
            return self::allocUserIdTags($user_id,$bv['bundle_id'],$bv['version']);
        });
    }

    /**根据user_id获取某个分组
     * @param $user_id
     * @param $name
     * @return mixed|string
     */
    static public function tagForNameByUserId($user_id,$name)
    {
        $tags = self::tagsForUserId($user_id);
        if(isset($tags[$name]))
            return $tags[$name];
        return 'X';
    }


    /**根据udid 生成分组
     * @param $udid
     * @param $bundle_id
     * @param $version
     * @return array
     */
    static public function allocUdidIdTags($udid,$bundle_id,$version)
    {
        $record = ABTestUdid::query()->where('udid',$udid)->first();
        $is_user_new = false;
        if(is_null($record))
        {
            $is_user_new = true;
            $record = new ABTestUdid();
            $record['udid'] = $udid;
        }
        $table_choose = 'udid';
        $names = self::testNames($table_choose,$bundle_id,$version);
        $data = [];
        $divide_group = false;
        foreach ($names as $name)
        {
            $config = self::configForName($name);
            /** 测试已经有结果了 */
            if($config['result'])
            {
                $data[$name] = $config['result'];
                continue;
            }
            /** 已经分配 */
            if(isset($record[$name]))
            {
                $data[$name] = $record[$name];
            }
            else
            {
                $end_time = strtotime($config['end_at']);
                if($config['target'] == 1 && $is_user_new == false)
                {
                    $record[$name] = 'X';
                }elseif (time() > $end_time){
                    $record[$name] = 'X';
                }
                else
                {
                    $record[$name] = self::randomFromConfig($config,$udid);
                }
                $divide_group = true;
                $data[$name] = $record[$name];
            }
        }
        /** 有新加分组信息 而且 允许保存到数据库（尚未处于修改数据表状态） */
        if($divide_group && self::allowAlloc(self::Type_Udid))
        {
            try
            {
                $record->save();
            }
            catch (\Exception $exception)
            {

            }

        }
        return $data;
    }

    static public function allocPushTags()
    {

    }
}
