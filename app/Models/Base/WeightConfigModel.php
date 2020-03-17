<?php

namespace App\Models\Base;

use App\Services\Base\MathService;
use Illuminate\Database\Eloquent\Model;
use App\Services\Base\RedisService;

class WeightConfigModel extends Model
{
    /**
     * 根据权重进行伪随机取mail_message_configs和push_message_configs中的配置
     * @param $feature
     * @param $bundle_id
     * @param array $other_columns
     * @return mixed
     */
    static function getConfigByFAndB($feature,$bundle_id,$other_columns = [])
    {
        $builder = (new static())->newQuery()->where('feature',$feature)
            ->whereIn('bundle_id',[$bundle_id,'custom'])
            ->where('status',1);
        foreach ($other_columns as $key => $value)
        {
            $builder->where($key,$value);
        }
        $configs = $builder->get()->toArray();
        $total_weight = 0;//总权重
        $mix_common_divisor = -1;//最小公约数
        $weight_key = get_class(new static())."message_configs_".$feature.$bundle_id.json_encode($other_columns);
        $weight_val = RedisService::center()->get($weight_key);
        if ($weight_val === null)
        {
            $weight_val = 1;
        }
        foreach ($configs as $config)
        {
            $total_weight += $config['weight'];
            if($mix_common_divisor > 0)
            {
                $mix_common_divisor = MathService::getMixCommonDivisor($mix_common_divisor,$config['weight']);
            }
            else
            {
                $mix_common_divisor = $config['weight'];
            }
        }
        $total_weight = $total_weight / $mix_common_divisor;
        $mix_coprime = MathService::getMixCoprimeNum(ceil($total_weight / 2),$total_weight);//取最小互质数
        $weight_limit = ($weight_val * $mix_coprime) % $total_weight;//控制取到权重
        $weight_val++;
        $weight_val = $weight_val > $total_weight ? $weight_val - $total_weight : $weight_val;
        RedisService::center()->setex($weight_key,86000*7,$weight_val);
        foreach ($configs as $config)
        {
            if($weight_limit < $config['weight'] / $mix_common_divisor)
            {
                return $config;
            }
            else
            {
                $weight_limit -= $config['weight'] / $mix_common_divisor;
            }
        }
        return null;
    }
}
