<?php

namespace App\Services\Base;

use App\Models\Base\Simple\RandomItemConfig;

class RandomItemService
{
    static protected $configCache = [];
    /**获取配置,分为隐藏weight和不隐藏weight
     * @param $name
     * @param bool $hide_weight
     * @return mixed
     */
    static function itemsForName($name,$hide_weight = true)
    {
        if(isset(self::$configCache[$name.$hide_weight]))
        {
            return self::$configCache[$name.$hide_weight];
        }
        $configs = RedisService::cacheObject("random_item_for_name",[$name,$hide_weight],function () use ($name,$hide_weight){
            $keys = ['order','type','amount'];
            if($hide_weight == false)
            {
                $keys[] = 'weight';
            }
            return RandomItemConfig::query()->where('name',$name)->get($keys)->toArray();
        });
        self::$configCache[$name.$hide_weight] = $configs;
        return $configs;
    }

    /**随机获取一项。  other_limit 示例:    ["21" => 2],表示获得21这个物品数量要小于2
     * @param $name
     * @param null $limit_money
     * @param null $limit_chips
     * @param null $other_limits
     * @return mixed
     */
    static function randomForName($name,$limit_money = null,$limit_chips = null,$other_limits = null)
    {
        $items = self::itemsForName($name,false);
        $filter_items = [];
        $total_weight = 0;
        foreach ($items as $item)
        {
            $type = $item['type'];
            $amount = $item['amount'];
            if(!is_null($limit_money) && $type == 1 && $amount >= $limit_money)
                continue;
            if(!is_null($limit_chips) && $type == 2 && $amount >= $limit_chips)
                continue;
            if($other_limits && isset($other_limits[$type]) && $amount >= $other_limits[$type])
                continue;
            $filter_items[] = $item;
            /** 最多配置为4位小数 */
            $total_weight += $item['weight'] * 10000;
        }
        $weight = rand(0,$total_weight - 1);
        foreach ($filter_items as $item)
        {
            if($weight < $item['weight'] * 10000)
                return $item;
            $weight -= $item['weight'];
        }
        /**
         * 这里需要发出警报，说明代码有问题
         */
        return $items[0];
    }
}
