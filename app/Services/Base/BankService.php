<?php

namespace App\Services\Base;

use App\Models\Base\UserBalance;

class BankService
{
    //用户bank缓存
    static protected $bank_cache = [];

    static function newUser($user_id,$bundle_id,$version)
    {
        $balance = UserBalance::find($user_id);
        $balance['cash_merge'] = PlatformConfigService::configForKey("cash_merge",$bundle_id,$version);
        $balance->save();
    }

    /**
     * 初始值为$merge,重新计算bank
     * @param $user_id
     * @param $merge
     */
    static function reBuildBank($user_id,$merge = 0)
    {
        $bv = UserCacheService::bundleAndVersion($user_id);
        $keys = [
            "thanksgiving_version" => 0,
            "egg_version" => 0,
            "slots_version" => 0,
            "wheel_version" => 0
        ];
        PlatformConfigService::configForKeys(array_keys($keys),$bv['bundle_id'],$bv['version'],$keys);
        $balance = UserBalance::find($user_id);
        $bank = $balance['cash_merge'];
        //$bank += WheelGameService::bankerForUser($user_id);
        //$bank += WheelGameService::bankerForUser($user_id);
        //$bank += WheelGameService::bankerForUser($user_id);
        //$bank += WheelGameService::bankerForUser($user_id);
        self::setUserBank($user_id,$bank);
    }

    static function setUserBank($user_id,$bank)
    {
        $balance = UserBalance::find($user_id);
        $balance['bank'] = $bank;
        $balance->save();

        $key = "uc_user_bank".$user_id;
        $expire_time = 86400;
        $data = $bank;
        RedisService::center()->setex($key,$expire_time,$data);
        self::$bank_cache[$user_id] = $bank;
    }

    //获取用户bank
    /**
     * @param $user_id
     * @return array|mixed
     */
    static public function userBank($user_id)
    {
        if(isset(self::$bank_cache[$user_id])){
            return self::$bank_cache[$user_id];
        }else{
            $key = "uc_user_bank".$user_id;
            $bank = RedisService::center()->get($key);
            if(is_null($bank))
            {
                //查询未确定
                $res = UserBalance::query()->where('id',$user_id)->first();
                if (!empty($res)){
                    self::setUserBank($user_id,$res['bank']);
                    return $res['bank'];
                }else{
                    //未查询到处理
                    return [];
                }
            }
            else
            {
                return $bank;
            }

        }
    }

    static function limitMoney($user_id,$fortune = null)
    {
        $cash_limit = self::cashLimit($user_id);
        if(is_null($fortune))
        {
            $fortune = self::userBank($user_id) + UserCacheService::balance($user_id)['money'];
        }
        return $cash_limit - $fortune;
    }

    /**
     * 后续可能会根据user_id给不同的值
     * @param $user_id
     * @return int
     */
    static function cashLimit($user_id)
    {
        return 10;
    }

    /**
     * 根据item更新bank
     * @param $user_id
     * @param $item
     */
    static function appendItem($user_id,$item)
    {
        if($item['type'] == 1)
        {
            self::setUserBank($user_id,self::userBank($user_id) + $item['amount']);
        }
    }
}
