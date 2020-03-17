<?php

namespace App\Services\Base;

use Illuminate\Support\Facades\DB;
class IPLocationService
{
    /**
     * 根据IP获取国家
     * @param $ip
     * @return mixed|string
     */
    static public function queryCountry($ip)
    {
        $nums = explode('.',$ip);
        $number = 0;
        foreach ($nums as $num)
        {
            $number = $number * 256 + (int)$num;
        }
        $ip_search = $nums[0].".".$nums[1].".".$nums[2];
        $data = DB::select("select `country` from ip_fix where `ip` = '$ip_search'");
        if(count($data))
        {
            $country = (array)$data[0];
            return $country['country'];
        }
        $result = DB::select("select `country_code` from ip2location where ip_to >= $number limit 1");
        if(count($result))
        {
            $country = (array)$result[0];
            if ($country['country_code'] != "-")
            {
                return $country['country_code'];
            }
        }
        return 'US';
    }
}
